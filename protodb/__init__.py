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

from pathlib import Path

from .protocoldatabase import ProtocolDatabase


def connect(conn_string) -> ProtocolDatabase:
    """
    Open a protocol database connection. For the time being, the connections are to be assumed NOT thread-safe.

    :param conn_string:
    :return:
    """
    if conn_string.startswith('mysql:'):
        from .sqlprotocoldatabase import SqlProtocolDatabase

        return SqlProtocolDatabase(conn_string[6:])
    elif Path(conn_string).exists() and conn_string.endswith('.json'):
        from .jsonprotocoldatabase import JsonProtocolDatabase

        return JsonProtocolDatabase.with_path(conn_string)
    else:
        raise Exception('No clue how to understand connection string ' + conn_string)
