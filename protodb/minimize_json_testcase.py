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

import copy
import importlib
import json
from pathlib import Path
import sys
import yaml

from .jsonprotocoldatabase import JsonProtocolDatabase

if __name__ == "__main__":
    MODULE_NAME, TEST_FUNCTION, INPUT = sys.argv[1:]
    print("reducing test case for", MODULE_NAME, TEST_FUNCTION)

    test_module = importlib.import_module(MODULE_NAME)

    dir = Path(test_module.__file__).parent
    func = getattr(test_module, TEST_FUNCTION)

    json_path = INPUT
    output_path = dir / (func.__name__ + ".min.yml")

    print("input", json_path)

    with open(json_path, "rt") as f:
        the_model = json.load(f)

    pos = 0
    started_from_beginning = True
    initial_size = len(json.dumps(the_model))


    # returns index+1 on success
    def remove_depth_first(model, index, curr=0, path="", set_value_func=None):
        if isinstance(model, dict):
            for key, value in model.items():
                subpath = path + "." + str(key)

                if curr == index:
                    print(f"note: deleting {subpath}")
                    del model[key]
                    return curr + 1

                curr += 1

                # try to recurse
                def set_value(val):
                    model[key] = val

                curr = remove_depth_first(model[key], index, curr, subpath, set_value)

                if curr > index:
                    return curr
        elif isinstance(model, list):
            for i, value in enumerate(model):
                subpath = path + "[" + str(i) + "]"

                if curr == index:
                    print(f"note: deleting {subpath}")
                    del model[i]
                    return curr + 1

                curr += 1

                # try to recurse
                def set_value(val):
                    model[i] = val

                curr = remove_depth_first(model[i], index, curr, subpath, set_value)

                if curr > index:
                    return curr
        elif isinstance(model, int) and abs(model) > 1:
            # reduce integer to 1
            # TODO: this can be done more gradually, e.g. 42 -> 40 -> 30 -> 20 -> 10 -> 9 -> ... -> 1 -> 0
            if curr == index:
                print(f"note: reducing integer {path} to '1'")
                set_value_func(1)
                return curr + 1
        elif isinstance(model, str) and len(model) > 1:
            # reduce string to "A"
            if curr == index:
                print(f"note: reducing string {path} to 'A'")
                set_value_func("A")
                return curr + 1

            curr += 1

        return curr


    def run_test_for_model(model):
        db = JsonProtocolDatabase.from_model(model)
        func(db)


    # sanity check
    run_test_for_model(the_model)

    while True:
        # try to remove Nth element in depth-first expansion
        print(f"minimize: remove #{pos}")
        the_copy = copy.deepcopy(the_model)
        idx = remove_depth_first(the_copy, pos)

        if idx <= pos:
            if started_from_beginning:
                print("minimize: tree exhausted; done")
                break
            else:
                print("minimize: tree exhausted; retry from beginning")

                started_from_beginning = True
                pos = 0
                continue

        # invoke test
        try:
            run_test_for_model(the_copy)

            print("minimize: TEST PASS")

            # ok! delete permanently, do not increment pos, and mark that we are allowed to re-try all
            started_from_beginning = False
            the_model = the_copy
            continue
        except BaseException as ex:
            print("minimize: TEST FAIL: " + repr(ex))

        pos += 1

    print()

    final_size = len(json.dumps(the_model))
    print(f"reduced from {initial_size / 1000:.1f}k to {final_size / 1000:.1f}k ({(final_size - initial_size) / initial_size * 100:+.2f} %)")

    print("saving", output_path)

    with open(output_path, "wt") as f:
        yaml.safe_dump(the_model, f, sort_keys=False)
