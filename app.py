"""
=============================================================================
AI-Based Disaster Evacuation & Safe Route Planner
app.py  --  Python AI Engine (Flask)
=============================================================================
Implements:
  1. Data Processing + Logistic Regression       -> /api/predict-flood
  2. Graph Search + Heuristics + A* Search        -> /api/safe-route
  3. Constraint Satisfaction Problem (CSP)        -> /api/recommend-shelter

Data source: MySQL database created by schema.sql (aquaevac_db)
=============================================================================
"""

import os
import math
import traceback

import numpy as np
import requests
import mysql.connector
from mysql.connector import Error as MySQLError

from flask import Flask, request, jsonify
from flask_cors import CORS

from sklearn.linear_model import LogisticRegression
from sklearn.preprocessing import StandardScaler

import networkx as nx
from constraint import Problem


# =============================================================================
# CONFIG
# =============================================================================

DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "localhost"),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASSWORD", ""),
    "database": os.environ.get("DB_NAME", "aquaevac_db"),
}

# Optional: set this to a real OpenWeatherMap API key (free tier) to enable
# the "use live weather" button on the flood-prediction form. Elevation
# lookups use Open-Elevation, which is free and needs no key. Without a key,
# /api/live-weather returns a clear error and the form still works with
# manually typed numbers.
OPENWEATHER_API_KEY = os.environ.get("OPENWEATHER_API_KEY", "")
OPENWEATHER_URL = "https://api.openweathermap.org/data/2.5/weather"
OPEN_ELEVATION_URL = "https://api.open-elevation.com/api/v1/lookup"

# Weights used to convert (distance, hazard, traffic) into a single dynamic
# routing cost / heuristic penalty. Tunable.
HAZARD_WEIGHT = 1.5
TRAFFIC_WEIGHT = 0.8

# Default CSP thresholds (can be overridden per-request)
DEFAULT_MAX_DISTANCE_KM = 12.0
DEFAULT_HAZARD_LIMIT = 0.6          # avg hazard_score along access route must be <= this
HIGH_RISK_HAZARD_CUTOFF = 0.6       # edges at/above this are treated as "high flood risk zones"

# Risk level label mapping for the logistic regression classifier
RISK_LABELS = {0: "Low", 1: "Medium", 2: "High", 3: "Severe"}

# Static lookup of approximate coordinates for each named node in
# roads_graph. In a production system these would live in their own
# `graph_nodes` table; they are kept in-code here to match the node
# names seeded in schema.sql.
NODE_COORDS = {
    "Mirpur":      (23.8223, 90.3654),
    "Kazipara":    (23.8151, 90.3661),
    "Shewrapara":  (23.8065, 90.3673),
    "Dhanmondi":   (23.7461, 90.3742),
    "NewMarket":   (23.7331, 90.3852),
    "OldTown":     (23.7104, 90.4074),
    "Kotwali":     (23.7065, 90.4103),
    "Sadarghat":   (23.7038, 90.4088),
    "Uttara":      (23.8759, 90.3795),
    "Airport":     (23.8433, 90.4008),
    "Banani":      (23.7936, 90.4043),
    "Gulshan":     (23.7808, 90.4152),
    "Badda":       (23.7805, 90.4265),
    "Rampura":     (23.7580, 90.4256),
    "Malibagh":    (23.7484, 90.4127),
    "Motijheel":   (23.7331, 90.4172),
    "Mohammadpur": (23.7657, 90.3585),
    "Shyamoli":    (23.7746, 90.3654),
    "Tejgaon":     (23.7626, 90.3931),
    "Farmgate":    (23.7566, 90.3873),
}


# =============================================================================
# DATABASE HELPER
# =============================================================================

def get_db_connection():
    """Open a fresh MySQL connection using DB_CONFIG."""
    return mysql.connector.connect(**DB_CONFIG)


def fetch_all(query, params=None):
    """Run a SELECT query and return rows as a list of dicts."""
    conn = get_db_connection()
    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params or ())
        rows = cursor.fetchall()
        cursor.close()
        return rows
    finally:
        conn.close()


def haversine_km(coord1, coord2):
    """Great-circle distance between two (lat, lon) points, in kilometers."""
    lat1, lon1 = coord1
    lat2, lon2 = coord2
    R = 6371.0
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    d_phi = math.radians(lat2 - lat1)
    d_lambda = math.radians(lon2 - lon1)
    a = (math.sin(d_phi / 2) ** 2
         + math.cos(phi1) * math.cos(phi2) * math.sin(d_lambda / 2) ** 2)
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return R * c


def nearest_node(lat, lon):
    """Map an arbitrary (lat, lon) to the closest known graph node."""
    best_node, best_dist = None, float("inf")
    for node, coord in NODE_COORDS.items():
        d = haversine_km((lat, lon), coord)
        if d < best_dist:
            best_node, best_dist = node, d
    return best_node, best_dist


# =============================================================================
# CONCEPT 1: DATA PROCESSING + LOGISTIC REGRESSION  (flood risk prediction)
# =============================================================================

# The model is cached for speed, but the dataset signature is also cached.
# If an admin changes flood_data, the signature changes and the model retrains.
_flood_model_cache = {"model": None, "scaler": None, "signature": None}


def _map_risk_to_class(risk_level):
    """Keep the database's four risk levels as four real ML classes."""
    mapping = {
        "low": 0,
        "moderate": 1,
        "high": 2,
        "severe": 3,
    }
    return mapping.get(str(risk_level).lower(), 1)


def get_flood_data_signature():
    """Return a small fingerprint that changes when flood training data changes."""
    rows = fetch_all(
        "SELECT COUNT(*) AS row_count, "
        "COALESCE(MAX(id), 0) AS max_id, "
        "COALESCE(MAX(recorded_at), '1970-01-01 00:00:00') AS latest_recorded_at "
        "FROM flood_data"
    )
    row = rows[0]
    return (int(row["row_count"]), int(row["max_id"]), str(row["latest_recorded_at"]))


def invalidate_flood_model():
    """Clear the cached model so the next prediction trains from current data."""
    _flood_model_cache["model"] = None
    _flood_model_cache["scaler"] = None
    _flood_model_cache["signature"] = None


def train_flood_model():
    """
    DATA PROCESSING:
      - Pulls raw environmental readings from flood_data
      - Scales/normalizes features (rainfall_mm, river_level_m, elevation_m)
        using StandardScaler (zero mean, unit variance)

    LOGISTIC REGRESSION:
      - Trains a multi-class scikit-learn LogisticRegression classifier
        on the scaled features to predict flood risk class (0/1/2/3)
    """
    rows = fetch_all(
        "SELECT rainfall_mm, river_level_m, elevation_m, risk_level FROM flood_data"
    )
    if len(rows) < 5:
        raise RuntimeError(
            "Not enough flood_data rows to train the logistic regression model."
        )

    X_raw = np.array(
        [[r["rainfall_mm"], r["river_level_m"], r["elevation_m"]] for r in rows],
        dtype=float,
    )
    y = np.array([_map_risk_to_class(r["risk_level"]) for r in rows], dtype=int)

    # --- Data Processing: feature scaling/normalization ---
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X_raw)

    # --- Model training ---
    # (scikit-learn >= 1.5 auto-selects multinomial handling for
    # multi-class targets, so no explicit multi_class= kwarg is needed)
    model = LogisticRegression(max_iter=1000)
    model.fit(X_scaled, y)

    _flood_model_cache["model"] = model
    _flood_model_cache["scaler"] = scaler
    _flood_model_cache["signature"] = get_flood_data_signature()
    return model, scaler


def get_flood_model():
    """Return a current model; automatically retrain after data changes."""
    current_signature = get_flood_data_signature()
    if (
        _flood_model_cache["model"] is None
        or _flood_model_cache["scaler"] is None
        or _flood_model_cache["signature"] != current_signature
    ):
        train_flood_model()
    return _flood_model_cache["model"], _flood_model_cache["scaler"]


# =============================================================================
# CONCEPT 2: GRAPH SEARCH + HEURISTICS + A*  (safe route planning)
# =============================================================================

def build_road_graph():
    """
    Builds a NetworkX undirected graph from roads_graph.
    Edges flagged is_blocked = TRUE are excluded entirely, so A* can never
    route through a blocked road.

    Each edge stores:
      - distance_km      : raw physical distance (base g-cost component)
      - hazard_score      : 0..1 flood/hazard danger
      - traffic_density    : 0..1 congestion level
      - cost              : dynamic weighted cost used by A* as edge weight
                              cost = distance_km * (1 + HAZARD_WEIGHT*hazard
                                                       + TRAFFIC_WEIGHT*traffic)
    """
    rows = fetch_all(
        """SELECT node_a, node_b, distance_km, hazard_score, traffic_density
           FROM roads_graph
           WHERE is_blocked = FALSE"""
    )

    G = nx.Graph()
    for node in NODE_COORDS:
        G.add_node(node)

    for r in rows:
        distance = float(r["distance_km"])
        hazard = float(r["hazard_score"])
        traffic = float(r["traffic_density"])
        dynamic_cost = distance * (1 + HAZARD_WEIGHT * hazard + TRAFFIC_WEIGHT * traffic)

        G.add_edge(
            r["node_a"], r["node_b"],
            distance_km=distance,
            hazard_score=hazard,
            traffic_density=traffic,
            cost=dynamic_cost,
        )
    return G


def make_heuristic(G):
    """
    Build a safe A* heuristic.

    Road distances in a demo graph are data, so we do not blindly assume
    every stored road distance is longer than the straight-line distance.
    We calculate the smallest observed road/geographic ratio and multiply
    the straight-line distance by that ratio. The result stays admissible
    for this graph while remaining more useful than h(n)=0.
    """
    ratios = []
    for u, v, data in G.edges(data=True):
        if u in NODE_COORDS and v in NODE_COORDS:
            geo = haversine_km(NODE_COORDS[u], NODE_COORDS[v])
            road = float(data.get("distance_km", 0.0))
            if geo > 0 and road > 0:
                ratios.append(road / geo)

    distance_scale = min(1.0, min(ratios)) if ratios else 0.0

    def heuristic(u, v):
        if u not in NODE_COORDS or v not in NODE_COORDS:
            return 0.0
        return haversine_km(NODE_COORDS[u], NODE_COORDS[v]) * distance_scale

    return heuristic

def edge_weight(u, v, d):
    """Weight function handed to nx.astar_path -- uses the precomputed
    dynamic 'cost' (distance + hazard/traffic penalty) rather than raw
    distance, so the search itself is hazard-aware, not just the heuristic."""
    return d.get("cost", d.get("distance_km", 1.0))


def compute_safe_route(start, end):
    """Runs A* Search (nx.astar_path) over the hazard-aware graph."""
    G = build_road_graph()

    if start not in G.nodes or end not in G.nodes:
        raise ValueError(f"Unknown node(s): '{start}' and/or '{end}'.")

    heuristic = make_heuristic(G)

    try:
        path = nx.astar_path(G, start, end, heuristic=heuristic, weight=edge_weight)
    except nx.NetworkXNoPath:
        raise LookupError(
            f"No safe route exists between '{start}' and '{end}' "
            f"(all connecting roads are blocked or disconnected)."
        )

    total_distance = 0.0
    total_cost = 0.0
    hazard_scores = []
    traffic_scores = []

    for a, b in zip(path[:-1], path[1:]):
        edge = G[a][b]
        total_distance += edge["distance_km"]
        total_cost += edge["cost"]
        hazard_scores.append(edge["hazard_score"])
        traffic_scores.append(edge["traffic_density"])

    return {
        "path": path,
        "total_distance_km": round(total_distance, 3),
        "total_dynamic_cost": round(total_cost, 3),
        "avg_hazard_score": round(sum(hazard_scores) / len(hazard_scores), 3) if hazard_scores else 0.0,
        "avg_traffic_density": round(sum(traffic_scores) / len(traffic_scores), 3) if traffic_scores else 0.0,
        "hops": len(path) - 1,
    }


# =============================================================================
# CONCEPT 3: CONSTRAINT SATISFACTION PROBLEM (CSP)  (shelter recommendation)
# =============================================================================

def route_is_safe(G, start_node, end_node, hazard_limit):
    """
    Find a route that satisfies the shelter safety rules FIRST.

    High-hazard edges are removed before pathfinding. A* then minimizes the
    normal dynamic distance/traffic cost among the remaining safe edges.
    """
    safe_graph = G.copy()
    for u, v, data in list(safe_graph.edges(data=True)):
        if float(data.get("hazard_score", 0.0)) >= HIGH_RISK_HAZARD_CUTOFF:
            safe_graph.remove_edge(u, v)

    try:
        path = nx.astar_path(
            safe_graph,
            start_node,
            end_node,
            heuristic=make_heuristic(safe_graph),
            weight=edge_weight,
        )
    except (nx.NetworkXNoPath, nx.NodeNotFound):
        return False, None, None

    hazards = [
        float(safe_graph[a][b]["hazard_score"])
        for a, b in zip(path[:-1], path[1:])
    ]
    avg_hazard = sum(hazards) / len(hazards) if hazards else 0.0

    if avg_hazard > hazard_limit:
        return False, avg_hazard, path

    return True, avg_hazard, path

def solve_shelter_csp(evacuee_lat, evacuee_lon, max_distance_km, hazard_limit):
    """
    CSP FORMULATION
      Variable   : "shelter" (the shelter assigned to this evacuee)
      Domain     : IDs of shelters currently in the database
      Constraints:
        (a) Capacity constraint : current_occupancy + 1 <= capacity, status == 'open'
        (b) Distance constraint : haversine(evacuee, shelter) <= max_distance_km
        (c) Hazard constraint   : access route (evacuee -> shelter, via the
                                    road graph) must not pass through a
                                    high-flood-risk edge and must keep
                                    average hazard <= hazard_limit

    Uses the `python-constraint` library's backtracking solver
    (Problem / addVariable / addConstraint) to find every value in the
    domain that is consistent with all constraints, then ranks the
    consistent solutions to pick the single best shelter.
    """
    shelters = fetch_all(
        "SELECT id, name, latitude, longitude, capacity, current_occupancy, status FROM shelters"
    )
    if not shelters:
        return None, [], "No shelters exist in the database."

    shelters_by_id = {s["id"]: s for s in shelters}
    G = build_road_graph()
    evac_node, _ = nearest_node(evacuee_lat, evacuee_lon)

    # ---- Precompute constraint-check data per shelter (used by the CSP
    # constraint functions and later for ranking/explanation) ----
    details = {}
    for s in shelters:
        distance_km = haversine_km((evacuee_lat, evacuee_lon), (float(s["latitude"]), float(s["longitude"])))
        shelter_node, _ = nearest_node(float(s["latitude"]), float(s["longitude"]))
        safe, avg_hazard, path = route_is_safe(G, evac_node, shelter_node, hazard_limit)
        details[s["id"]] = {
            "distance_km": distance_km,
            "avg_hazard": avg_hazard,
            "access_route": path,
            "route_is_safe": safe,
        }

    # ---- Build and solve the CSP ----
    problem = Problem()
    problem.addVariable("shelter", list(shelters_by_id.keys()))

    def capacity_constraint(shelter_id):
        s = shelters_by_id[shelter_id]
        return s["status"] == "open" and (s["current_occupancy"] + 1) <= s["capacity"]

    def distance_constraint(shelter_id):
        return details[shelter_id]["distance_km"] <= max_distance_km

    def hazard_constraint(shelter_id):
        return details[shelter_id]["route_is_safe"]

    problem.addConstraint(capacity_constraint, ["shelter"])
    problem.addConstraint(distance_constraint, ["shelter"])
    problem.addConstraint(hazard_constraint, ["shelter"])

    solutions = problem.getSolutions()  # every shelter_id consistent with ALL constraints

    rejected = []
    consistent_ids = {sol["shelter"] for sol in solutions}
    for sid, s in shelters_by_id.items():
        if sid not in consistent_ids:
            reasons = []
            if not capacity_constraint(sid):
                reasons.append("capacity_or_status")
            if not distance_constraint(sid):
                reasons.append("too_far")
            if not hazard_constraint(sid):
                reasons.append("unsafe_route")
            rejected.append({"shelter_id": sid, "name": s["name"], "reasons": reasons})

    if not solutions:
        return None, rejected, "No shelter satisfies all constraints (capacity, distance, hazard)."

    # ---- Rank consistent solutions: prefer lower distance + lower hazard ----
    def score(sol):
        d = details[sol["shelter"]]
        return d["distance_km"] + (d["avg_hazard"] or 0.0) * 10  # hazard weighted heavier

    best = min(solutions, key=score)
    best_shelter = shelters_by_id[best["shelter"]]
    best_detail = details[best["shelter"]]

    result = {
        "shelter_id": best_shelter["id"],
        "name": best_shelter["name"],
        "latitude": float(best_shelter["latitude"]),
        "longitude": float(best_shelter["longitude"]),
        "capacity": best_shelter["capacity"],
        "current_occupancy": best_shelter["current_occupancy"],
        "distance_km": round(best_detail["distance_km"], 3),
        "avg_hazard_on_route": round(best_detail["avg_hazard"], 3) if best_detail["avg_hazard"] is not None else None,
        "access_route": best_detail["access_route"],
        "candidates_considered": len(shelters),
        "candidates_satisfying_constraints": len(solutions),
    }
    return result, rejected, None


# =============================================================================
# FLASK APP
# =============================================================================

app = Flask(__name__)
CORS(app)  # enable Cross-Origin Resource Sharing for all routes


@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "service": "disaster-evac-ai-engine"})


@app.route("/api/nodes", methods=["GET"])
def list_nodes():
    """Helper endpoint: lists valid road-graph node names for testing /api/safe-route."""
    return jsonify({"nodes": sorted(NODE_COORDS.keys())})


# -----------------------------------------------------------------------
# LIVE WEATHER + ELEVATION (real data, optional)
# -----------------------------------------------------------------------
@app.route("/api/live-weather", methods=["GET"])
def live_weather():
    """
    Pulls REAL current data for a coordinate, to auto-fill the flood
    prediction form instead of typing numbers by hand:
      - rainfall_mm : from OpenWeatherMap's current weather (rain.1h, mm),
                       0 if it isn't currently raining there. Requires
                       OPENWEATHER_API_KEY to be set.
      - elevation_m : from Open-Elevation (free, no API key needed).

    There is no free, standard public API for real-time river gauge level,
    so river_level_m is intentionally NOT auto-filled here — the person
    still enters that one themselves (or an admin wires up a real river
    authority feed later).

    GET /api/live-weather?latitude=..&longitude=..
    """
    try:
        lat = request.args.get("latitude", type=float)
        lon = request.args.get("longitude", type=float)
        if lat is None or lon is None:
            return jsonify({"error": "latitude and longitude query params are required."}), 400

        result = {"latitude": lat, "longitude": lon, "rainfall_mm": None, "elevation_m": None, "warnings": []}

        # --- Elevation (Open-Elevation, no key required) ---
        try:
            elev_resp = requests.get(OPEN_ELEVATION_URL, params={"locations": f"{lat},{lon}"}, timeout=5)
            elev_resp.raise_for_status()
            elev_data = elev_resp.json()
            result["elevation_m"] = float(elev_data["results"][0]["elevation"])
        except Exception as exc:
            result["warnings"].append(f"Elevation lookup failed: {exc}")

        # --- Rainfall (OpenWeatherMap, needs OPENWEATHER_API_KEY) ---
        if not OPENWEATHER_API_KEY:
            result["warnings"].append(
                "OPENWEATHER_API_KEY is not set on the server, so rainfall could not be fetched live. "
                "Enter rainfall manually, or set OPENWEATHER_API_KEY (see README)."
            )
        else:
            try:
                weather_resp = requests.get(
                    OPENWEATHER_URL,
                    params={"lat": lat, "lon": lon, "appid": OPENWEATHER_API_KEY, "units": "metric"},
                    timeout=5,
                )
                weather_resp.raise_for_status()
                weather_data = weather_resp.json()
                # OpenWeatherMap only reports rain volume when it's actually
                # raining; absence of "rain" means 0mm right now.
                result["rainfall_mm"] = float(weather_data.get("rain", {}).get("1h", 0.0))
                result["weather_description"] = (weather_data.get("weather") or [{}])[0].get("description")
                result["temperature_c"] = weather_data.get("main", {}).get("temp")
            except Exception as exc:
                result["warnings"].append(f"Live rainfall lookup failed: {exc}")

        return jsonify(result)

    except Exception as e:
        traceback.print_exc()
        return jsonify({"error": str(e)}), 500


# -----------------------------------------------------------------------
# 1) DATA PROCESSING + LOGISTIC REGRESSION
# -----------------------------------------------------------------------
@app.route("/api/predict-flood", methods=["POST"])
def predict_flood():
    try:
        payload = request.get_json(force=True) or {}
        required = ["rainfall_mm", "river_level_m", "elevation_m"]
        missing = [f for f in required if f not in payload]
        if missing:
            return jsonify({"error": f"Missing fields: {missing}"}), 400

        rainfall = float(payload["rainfall_mm"])
        river_level = float(payload["river_level_m"])
        elevation = float(payload["elevation_m"])

        model, scaler = get_flood_model()

        # --- Data Processing: normalize/scale the incoming raw input
        # using the SAME scaler fitted on the training data ---
        X_input = np.array([[rainfall, river_level, elevation]])
        X_scaled = scaler.transform(X_input)

        predicted_class = int(model.predict(X_scaled)[0])
        probabilities = model.predict_proba(X_scaled)[0]

        confidence = {
            RISK_LABELS[int(class_id)]: round(float(probability), 4)
            for class_id, probability in zip(model.classes_, probabilities)
        }

        return jsonify({
            "input": {
                "rainfall_mm": rainfall,
                "river_level_m": river_level,
                "elevation_m": elevation,
            },
            "predicted_risk_level": predicted_class,
            "risk_label": RISK_LABELS[predicted_class],
            "confidence": confidence,
        })

    except Exception as e:
        traceback.print_exc()
        return jsonify({"error": str(e)}), 500


# -----------------------------------------------------------------------
# 2) GRAPH SEARCH + HEURISTIC + A*
# -----------------------------------------------------------------------
@app.route("/api/safe-route", methods=["POST"])
def safe_route():
    try:
        payload = request.get_json(force=True) or {}
        start = payload.get("start")
        end = payload.get("end")
        if not start or not end:
            return jsonify({
                "error": "Both 'start' and 'end' node names are required.",
                "hint": "GET /api/nodes for the list of valid node names."
            }), 400

        result = compute_safe_route(start, end)
        return jsonify({
            "start": start,
            "end": end,
            **result,
        })

    except ValueError as e:
        return jsonify({"error": str(e)}), 400
    except LookupError as e:
        return jsonify({"error": str(e)}), 404
    except Exception as e:
        traceback.print_exc()
        return jsonify({"error": str(e)}), 500


# -----------------------------------------------------------------------
# 3) CONSTRAINT SATISFACTION PROBLEM (CSP)
# -----------------------------------------------------------------------
@app.route("/api/recommend-shelter", methods=["POST"])
def recommend_shelter():
    try:
        payload = request.get_json(force=True) or {}
        if "latitude" not in payload or "longitude" not in payload:
            return jsonify({"error": "Both 'latitude' and 'longitude' are required."}), 400

        lat = float(payload["latitude"])
        lon = float(payload["longitude"])
        max_distance_km = float(payload.get("max_distance_km", DEFAULT_MAX_DISTANCE_KM))
        hazard_limit = float(payload.get("hazard_limit", DEFAULT_HAZARD_LIMIT))

        best, rejected, error = solve_shelter_csp(lat, lon, max_distance_km, hazard_limit)

        if best is None:
            return jsonify({
                "error": error,
                "constraints_used": {
                    "max_distance_km": max_distance_km,
                    "hazard_limit": hazard_limit,
                },
                "rejected_shelters": rejected,
            }), 404

        return jsonify({
            "recommended_shelter": best,
            "constraints_used": {
                "max_distance_km": max_distance_km,
                "hazard_limit": hazard_limit,
            },
            "rejected_shelters": rejected,
        })

    except Exception as e:
        traceback.print_exc()
        return jsonify({"error": str(e)}), 500


# -----------------------------------------------------------------------
# ERROR HANDLERS
# -----------------------------------------------------------------------
@app.errorhandler(404)
def not_found(e):
    return jsonify({"error": "Endpoint not found."}), 404


@app.errorhandler(500)
def server_error(e):
    return jsonify({"error": "Internal server error."}), 500


# =============================================================================
# ENTRY POINT
# =============================================================================
if __name__ == "__main__":
    # Warm up the logistic regression model at startup so the first
    # /api/predict-flood request isn't slowed down by training.
    try:
        train_flood_model()
        print("[startup] Flood risk logistic regression model trained successfully.")
    except Exception as exc:
        print(f"[startup] WARNING: could not pre-train flood model: {exc}")

    app.run(host="127.0.0.1", port=5000, debug=False)
