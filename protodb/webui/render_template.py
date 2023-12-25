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

# usage: echo <JSON DATA> | render_template.py <TEMPLATE NAME>

import argparse
import json
from sys import stdin

from .common_jinja_env import env

parser = argparse.ArgumentParser()
parser.add_argument("template_name")
args = parser.parse_args()

template = env.get_template(args.template_name + ".html")

data = json.load(stdin)

try:
    env.globals.update(data["_globals"])
except KeyError:
    pass

print(template.render(**data))
