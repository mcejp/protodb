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

import os


class AuthMethodNoAuthorization:
    pass


class AuthMethodHeader:
    pass


def parse_auth_method(login_method_str: str):
    tokens = login_method_str.split(";")
    method = tokens[0]

    if method == "arbitrary":
        return AuthMethodNoAuthorization()
    elif method == "header":
        return AuthMethodHeader()
    else:
        raise Exception("PROTODB_LOGIN_METHOD missing or invalid")


conn_string = os.getenv("PROTODB_CONN_STRING")
assert conn_string and len(conn_string)
auth_method = parse_auth_method(os.getenv("PROTODB_LOGIN_METHOD"))
