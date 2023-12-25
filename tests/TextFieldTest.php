<?php declare(strict_types=1);
/*
 * Copyright (C) 2016-2023 Martin Cejp
 *
 * This file is part of ProtoDB.
 *
 * ProtoDB is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * ProtoDB is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ProtoDB.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'DiceTest.php';

/**
 * @covers \candb\ui\TextField
 */
final class TextFieldTest extends DiceTest
{
    public function testThatInvalidConversionToIntThrows(): void {
        $this->expectException(InvalidArgumentException::class);

        $field = new \candb\ui\TextField(null, \candb\ui\TextField::TYPE_INTEGER);
        $field->translate_form_data('foobar');
    }

    public function testThatRender2DWorksWithIntegersForViewing(): void {
        $this->expectOutputRegex("/3/");

        $form = new \candb\ui\Form('form', false);
        $field = new \candb\ui\TextField(null, \candb\ui\TextField::TYPE_INTEGER);
        $field->render2($form, 'field', 3);
    }

    public function testThatRender2DWorksWithIntegersForEditing(): void {
        $this->expectOutputRegex("/value='1337'/");

        $form = new \candb\ui\Form('form', true);
        $field = new \candb\ui\TextField(null, \candb\ui\TextField::TYPE_INTEGER);
        $field->render2($form, 'field', 1337);
    }

    public function testEmptyRequiredFieldIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);

        $field = new \candb\ui\TextField(null, \candb\ui\TextField::TYPE_TEXT);
        $field->makeRequired();
        $field->translate_form_data('');
    }

    public function testZeroIsDisplayedAsZero(): void {
        $this->expectOutputString('<div class="value">0</div>');

        $form = new \candb\ui\Form('form', false);
        $field = new \candb\ui\TextField(null, 'integer');
        $field->render2($form, 'field', 0);
    }

    public function testMinAllowedValue(): void {
        $field = new \candb\ui\TextField(null, \candb\ui\TextField::TYPE_INTEGER);
        $field->makeRequired();

        // Should work
        $field->translate_form_data('2');

        // Should fail
        $field->set_min_value(3);
        $this->expectException(InvalidArgumentException::class);
        $field->translate_form_data('2');
    }
}
