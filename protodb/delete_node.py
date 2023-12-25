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

import configargparse

from . import connect

# TODO: generalize to delete_object.py
# TODO: click-based CLI tool?

if __name__ == "__main__":
    parser = configargparse.ArgParser()
    parser.add_argument("fully_qualified_name", type=str)
    parser.add_argument('--db', dest='conn_string', env_var='PROTODB_CONN_STRING', required=True)
    args = parser.parse_args()

    db = connect(args.conn_string)

    db.transaction_begin()

    node = db.get_node(fully_qualified_name=args.fully_qualified_name)
    db.delete(node, who_changed="admin")

    db.transaction_commit()
