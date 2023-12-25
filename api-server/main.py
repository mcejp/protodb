#
# Copyright (C) 2016-2023 Martin Cejp
#
# This file is part of ProtoDB.
#
# ProtoDB is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# ProtoDB is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with ProtoDB.  If not, see <http://www.gnu.org/licenses/>.

import threading
import time
from typing import Optional
import urllib.request

from flask import Flask, abort, jsonify, request, Response

from protodb import connect
import config
import protodb.export.export_json2
import rbac


MIMETYPE_DBC = "application/vnd.dbc"

app = Flask(__name__)

time.sleep(3)   # FIXME: terrible work-around for issue #74
db = connect(config.conn_string)
db_lock = threading.Lock()


def defer_request(href):
    headers = {}
    for header in ["X-Auth-Roles", "X-Auth-Username"]:
        try:
            headers[header] = request.headers[header]
        except KeyError:
            pass

    r = urllib.request.urlopen(urllib.request.Request("http://localhost:10080" + href, headers=headers))

    if r.status != 200:
        abort(r.status)

    return r


def get_node_id(package_name, node_name) -> Optional[int]:
    with db_lock:
        package = db.get_package(package_name)
        candidates = [node for node in db.get_nodes(scope=package) if node.name == node_name]

        if len(candidates) < 1:
            return None

        return candidates[0].id


@app.route('/v1/packages')
@rbac.requires_role(rbac.API_READ_USER_ROLE)
def list_packages():
    with db_lock:
        packages = db.get_packages()

        return jsonify([dict(name=p.name) for p in packages])


@app.route("/v1/packages/<package_name>/with-relations")
@rbac.requires_role(rbac.API_READ_USER_ROLE)
def get_package_with_relations(package_name):
    with db_lock:
        package = db.get_package(package_name)

        model = protodb.export.export_json2.export_buses_of_package(package, db)

    return jsonify(model)


@app.route("/v1/packages/<package_name>/buses/<bus_name>")
@rbac.requires_role(rbac.API_READ_USER_ROLE)
def get_bus_dbc(package_name, bus_name):
    if request.accept_mimetypes[MIMETYPE_DBC]:
        with db_lock:
            package = db.get_package(package_name)
            candidates = [bus for bus in db.get_buses(scope=package) if bus.name == bus_name]

            if len(candidates) < 1:
                abort(404)

            bus_id = candidates[0].id

        # For the moment, just proxy to the existing implementation in PHP + Python.
        # An intermediate step could be to GET the JSON model and then call convert-to-dbc directly.
        r = defer_request(f"/buses/{bus_id}/export-dbc")

        body = r.read()
        return Response(body, mimetype=MIMETYPE_DBC)
    else:
        # 406 Not Acceptable
        abort(406)


@app.route("/v1/packages/<package_name>/nodes/<node_name>/code-tx.zip")
@rbac.requires_role(rbac.API_READ_USER_ROLE)
def generate_tx_code_for_node(package_name, node_name):
    # Resolve node ID
    node_id = get_node_id(package_name, node_name)

    if node_id is None:
        abort(404)

    # Proxy to the existing implementation in PHP + Python.
    # An intermediate step could be to GET the JSON model and then call candb-codegen directly.
    r = defer_request(f"/units/{node_id}/export-tx")

    body = r.read()
    return Response(body, mimetype="application/zip")


@app.route('/v1/ping')
def ping():
    return dict(server="ProtoDB API Server")


@app.route("/legacy/packages/<package_name>/nodes/<node_name>/with-relations")
@rbac.requires_role(rbac.API_READ_USER_ROLE)
def get_node_with_relations_jsonv1(package_name, node_name):
    # Resolve node ID
    node_id = get_node_id(package_name, node_name)

    if node_id is None:
        abort(404)

    # Proxy to the existing implementation in PHP. This is unlikely to be ported over.
    r = defer_request(f"/units/{node_id}/export-json")

    body = r.read()
    return Response(body, mimetype=r.headers["Content-Type"])
