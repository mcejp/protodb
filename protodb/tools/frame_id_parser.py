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

from dataclasses import dataclass
from enum import auto, Enum
import json
from typing import Optional, List

import yaml


def check(model, required_properties, allowed_properties):
    assert isinstance(model, dict)

    missing_properties = required_properties - set(model.keys())
    extra_properties = set(model.keys()) - allowed_properties

    if missing_properties or extra_properties:
        errors = []
        if len(missing_properties):
            errors.append("missing required properties: " + " ".join(missing_properties))
        if len(extra_properties):
            errors.append("unrecognized properties: " + " ".join(extra_properties))
        raise KeyError("; ".join(errors))


# TODO: DRY? This enumeration already exists somewhere
class FrameType(Enum):
    CAN_STD = auto()
    CAN_EXT = auto()


@dataclass
class FrameIdField:
    name: str
    lsb_pos: int
    bits: int
    min_value: int

    labels: Optional[List[str]] = None
    label_fmt: Optional[str] = None

    @staticmethod
    def with_labels(name: str, lsb_pos: int, bits: int, labels: List[str]):
        return FrameIdField(name=name, lsb_pos=lsb_pos, bits=bits, min_value=0, labels=labels)

    @staticmethod
    def with_label_template(name: str, lsb_pos: int, bits: int, label_fmt: str, min_value: int):
        return FrameIdField(name=name, lsb_pos=lsb_pos, bits=bits, min_value=min_value, label_fmt=label_fmt)

    def dict(self):
        return self.__dict__ | dict(flat_labels=self.flat_labels)

    @property
    def flat_labels(self):
        if self.labels:
            return self.labels
        else:
            return [self.label_fmt % i for i in range(self.min_value, self.min_value + 2**self.bits)]


@dataclass
class FrameIdType:
    name: str
    frame_type: FrameType
    fields: List[FrameIdField]

    def dict(self):
        return dict(name=self.name, frame_type=self.frame_type, fields=[field.dict() for field in self.fields])


if __name__ == "__main__":
    try:
        with open("config/frame-id-formats.yml") as f:
            model = yaml.safe_load(f)
    except FileNotFoundError:
        with open("config/frame-id-formats.default.yml") as f:
            model = yaml.safe_load(f)

    assert isinstance(model, list)

    types = []

    # FIXME: error reporting by line??
    for type_model in model:
        # print(type_model)

        # Note: we could also have a separate display_name, but it would make aliases a bit more work (and we = lazy)
        required_properties = {"name", "frame_type", "frame_id_fields"}
        check(type_model, required_properties, allowed_properties=required_properties | {"aliases"})

        assert type_model["name"] not in {"DIRECT", "DIRECT_EXTENDED", "UNDEF"}
        assert len(type_model["name"]) <= 16    # DB limitation
        assert type_model["frame_type"] == "CAN_STD"    # EXT not yet implemented
        assert isinstance(type_model["frame_id_fields"], list)

        total_bits = 0
        expected_bits = 11
        bit_pos = expected_bits

        fields = []

        for field_model in type_model["frame_id_fields"]:
            required_properties = {"name", "bits"}
            check(field_model, required_properties, allowed_properties=required_properties | {"label_fmt", "labels", "min_value"})

            try:
                assert isinstance(field_model["bits"], int) and field_model["bits"] > 0

                lsb_pos = bit_pos - field_model["bits"]

                if "labels" in field_model:
                    assert isinstance(field_model["labels"], list)
                    assert len(field_model["labels"]) == 2 ** field_model["bits"]
                    assert "label_fmt" not in field_model
                    assert "min_value" not in field_model

                    field = FrameIdField.with_labels(**field_model, lsb_pos=lsb_pos)
                elif "label_fmt" in field_model:
                    assert "labels" not in field_model

                    min_value = field_model["min_value"] if "min_value" in field_model else 0
                    assert isinstance(min_value, int)
                    field = FrameIdField.with_label_template(**{**field_model, "min_value": min_value}, lsb_pos=lsb_pos)

                fields.append(field)
                total_bits += field.bits
                bit_pos -= field.bits
            except Exception as e:
                raise Exception(f"Error in parsing type {type_model['name']}, field {field_model['name']}") from e

        assert total_bits == expected_bits

        del type_model["frame_id_fields"]
        if "aliases" in type_model:
            aliases = type_model["aliases"]
            del type_model["aliases"]
        else:
            aliases = []

        type = FrameIdType(**type_model, fields=fields)
        # print(type)
        types.append(type)

        for alias_name in aliases:
            type = FrameIdType(**{**type_model, "name": alias_name}, fields=fields)
            # print(type)
            types.append(type)

    # dump flat model
    print(json.dumps([type.dict() for type in types]))
