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

from enum import auto, Enum
import json
import re
from sys import stdin

from .common_jinja_env import env
from .. import connect, ProtocolDatabase


# https://stackoverflow.com/a/37697078
def split_camel_case(name):
    return re.sub('([A-Z][a-z]+)', r' \1', re.sub('([A-Z]+)', r' \1', name)).split()


def build_search_index(db: ProtocolDatabase):
    class EntityType(Enum):
        PACKAGE = auto()
        BUS = auto()
        NODE = auto()
        ENUM_TYPE = auto()
        MESSAGE = auto()
        MESSAGE_FIELD = auto()

    search_data = {"_index": {},        # mapping keyword -> list of EIDs
                   "_keywords": [],     # mapping EID -> list of keywords
                   "_allKeywords": [],  # list of all keywords, sorted
                   "_allTypes": {member.value: name for name, member in EntityType.__members__.items()},
                   # property arrays -- currently must be dense (index == EID)
                   "url": [],
                   "name": [],
                   "type": [],
                   }

    # URL serves as a unique ID
    def add_object(properties, keywords):
        keyword_tokens = set()

        for k in keywords:
            if "_" in k:
                # when there is an underscore, e.g. "Foo_Bar", add "FOO_BAR", as well as "FOO" and "BAR"
                upper = k.upper()
                keyword_tokens.update([upper, *upper.split("_")])
            else:
                # if there is no underscore, split on camel case
                keyword_tokens.update(token.upper() for token in split_camel_case(k))

        entity_id = len(search_data["_keywords"])
        search_data["_keywords"].append(list(keyword_tokens))

        for prop_name, value in properties.items():
            # if prop_name == "name": value = str(keyword_tokens)           # Uncomment this line to debug tokenization

            assert len(search_data[prop_name]) == entity_id
            search_data[prop_name].append(value)

        for key in keyword_tokens:
            try:
                search_data["_index"][key].append(entity_id)
            except KeyError:
                search_data["_index"][key] = [entity_id]

    all_packages = db.get_packages()

    # TODO: hardcoded path format
    for package in all_packages:
        add_object(dict(url=f"packages/{package.id}", name=package.fully_qualified_name, type=EntityType.PACKAGE.value), [package.name])

        for bus in db.get_buses(scope=package):
            add_object(dict(url=f"buses/{bus.id}", name=bus.fully_qualified_name, type=EntityType.BUS.value), [package.name, bus.name])

        for node in db.get_nodes(scope=package):
            add_object(dict(url=f"units/{node.id}", name=node.fully_qualified_name, type=EntityType.NODE.value), [package.name, node.name])

            for enum_type in db.get_enum_types(scope=node):
                add_object(dict(url=f"enum-types/{enum_type.id}", name=enum_type.fully_qualified_name, type=EntityType.ENUM_TYPE.value), [package.name, node.name, enum_type.name])

            for message in db.get_messages(scope=node):
                add_object(dict(url=f"messages/{message.id}", name=message.fully_qualified_name, type=EntityType.MESSAGE.value), [package.name, node.name, message.name])

                # Disabled for the moment, because it increases the size of the index massively
                # for message_field in db.get_message_fields(scope=message):
                #     add_object(dict(url=f"messages/{message.id}", name=message_field.fully_qualified_name, type=EntityType.MESSAGE_FIELD.value), [package.name, node.name, message.name, message_field.name])

    search_data["_allKeywords"] = list(sorted(search_data["_index"].keys()))
    return search_data


if __name__ == "__main__":
    import configargparse

    parser = configargparse.ArgParser()
    parser.add_argument('--db', dest='conn_string', env_var='PROTODB_CONN_STRING', required=True)
    args = parser.parse_args()

    db = connect(args.conn_string)

    template = env.get_template("dashboard.html")

    data = json.load(stdin)

    try:
        env.globals.update(data["_globals"])
    except KeyError:
        pass

    search_data = build_search_index(db)

    print(template.render(**data,
                          search_data=search_data))
