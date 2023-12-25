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

import functools
from typing import Iterable

import flask
from flask import jsonify

import config


API_READ_USER_ROLE = "candb.api_read_user"


def has_all_roles(request: flask.Request, required_roles: Iterable[str]) -> bool:
    auth_method = config.auth_method

    if isinstance(auth_method, config.AuthMethodNoAuthorization):
        return True
    elif isinstance(auth_method, config.AuthMethodHeader):
        # authorization HTTP proxy (fully trusted)
        user_roles = set(request.headers["X-Auth-Roles"].split(","))

        return set.issubset(set(required_roles), user_roles)
    else:
        raise Exception("This form of authorization is not implemented")


def requires_role(role_name):
    def apply(function):
        @functools.wraps(function)
        def wrapped(*args, **kwargs):
            if not has_all_roles(flask.request, {role_name}):
                return jsonify(type="protodb/required-role-missing", title="This user is not authorized to use the service"), \
                       403

            return function(*args, **kwargs)

        return wrapped

    return apply
