$.fn.insertAt = function(index, $parent) {
    return this.each(function() {
        if (index === 0) {
            $parent.prepend(this);
        } else {
            $parent.children().eq(index - 1).after(this);
        }
    });
}

$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip(); 
    $('[data-toggle="popover"]').popover(); 
});

function form_table_update(table, columns, ordered) {
    var tbody = table.children('tbody');
    var numRows = tbody.children().length - 1;
    var name = table.attr('id');

    var allData = [];

    for (var row = 0; row < numRows; row++) {
        var tr = $(tbody.children()[row]);
        var rowData = {};

        for (var i = 0; i < columns.length; i++) {
            var td = $(tr.children()[i]);

            switch (columns[i].type) {
                case 'enum':
                    var input = td.children('select');
                    rowData[columns[i].name] = JSON.parse(input.val());
                    break;

                case 'integer':
                    var input = td.children('input');
                    rowData[columns[i].name] = parseInt(input.val());
                    break;

                case 'real':
                case 'text':
                    var input = td.children('input');
                    rowData[columns[i].name] = input.val();
                    break;
            }
        }

        rowData['id'] = tr.attr('data-id');
        allData.push(rowData);
    }

    $('input[name="' + name + '"]').val(JSON.stringify(allData));
}

class FormTable {
    constructor(table, columns, ordered) {
        this.table = table;
        this.columns = columns;
        this.ordered = ordered;

        var name = table.attr('id');
        var allData = JSON.parse($('input[name="' + name + '"]').val());

        for (var row = 0; row < allData.length; row++)
            this.addRow(allData[row], false, row);

        table.find('.add-row').click(() => this.addRow(null, true));

        this.onRowAdded = null;
    }

    addRow(data, focus, index) {
        const table = this.table;
        const columns = this.columns;
        const ordered = this.ordered;

        var tbody = table.children('tbody');
        var numRows = tbody.children().length - 1;

        if (index === undefined)
            index = numRows;

        if (data === null && this.defaultRowData)
            data = this.defaultRowData;

        var tr = $('<tr>');

        for (var i = 0; i < columns.length; i++) {
            var td = $('<td>');

            switch (columns[i].type) {
                case 'enum':
                    var input = $("<select class='form-control'>");
                    const needOptgroups = (columns[i].option_groups.length !== 1 || columns[i].option_groups[0].label);

                    for (const group of columns[i].option_groups) {
                        const optgroup = needOptgroups ? $('<optgroup></optgroup>').attr('label', group.label) : input;

                        for (const [key, item_value] of group.options) {
                            optgroup.append($("<option></option>").text(item_value).attr('value', JSON.stringify(key)));
                        }

                        if (needOptgroups) {
                            input.append(optgroup);
                        }
                    }
                    if (data && data[columns[i].name] !== undefined)
                        input.val(JSON.stringify(data[columns[i].name]));
                    else if (columns[i].default)
                        input.val(JSON.stringify(columns[i].default));
                    td.append(input);

                    if (focus && i == 0)
                        setTimeout((function(input) { input.focus(); }).bind(this, input), 0);
                    break;

                case 'integer':
                    var input = $("<input type='number' step='1' class='form-control'>");
                    if (data && data[columns[i].name] !== undefined)
                        input.val(data[columns[i].name]);
                    if (columns[i].maxLength !== undefined)
                        input.attr('maxlength', columns[i].maxLength);
                    if (columns[i].min !== undefined)
                        input.attr('min', columns[i].min);
                    if (columns[i].required)
                        input.attr('required', 'required');
                    td.append(input);

                    if (focus && i == 0)
                        setTimeout((function(input) { input.focus(); }).bind(this, input), 0);
                    break;

                case 'real':
                case 'text':
                    var input = $("<input type='text' class='form-control'>");
                    if (data && data[columns[i].name] !== undefined)
                        input.val(data[columns[i].name]);
                    if (columns[i].maxLength !== undefined)
                        input.attr('maxlength', columns[i].maxLength);
                    if (columns[i].required)
                        input.attr('required', 'required');
                    td.append(input);

                    if (focus && i == 0)
                        setTimeout((function(input) { input.focus(); }).bind(this, input), 0);
                    break;
            }

            tr.append(td);
        }

        td = $("<td>");
        var btnGroup = $("<div class='btn-group'>");

        btnGroup.append($("<button type='button' class='btn btn-default' title='Insert before'><span class='glyphicon glyphicon-plus'></span></button>").click((e) => {
            this.addRow(null, true, $(e.target).closest('tr').index());
        }));

        if (ordered) {
            btnGroup.append($("<button type='button' class='btn btn-default' title='Move up'><span class='glyphicon glyphicon-chevron-up'></span></button>").click(function () {
                var tr = $(this).closest('tr');
                var index = tr.index();

                if (index > 0)
                    tr.insertAt(index - 1, tbody);
            }));
        }

        btnGroup.append($("<button type='button' class='btn btn-danger' title='Remove'><span class='glyphicon glyphicon-remove'></span></button>").click(function() {
            $(this).closest('tr').remove();
            $('form[data-toggle="validator"]').validator('update');
        }));

        td.append(btnGroup);
        tr.append(td);

        if (data && data['id'])
            tr.attr('data-id', data['id']);

        tr.insertAt(index, tbody);
        $('form[data-toggle="validator"]').validator('update');
        return tr;
    }

    setDefaultRowData(defaultRowData) {
        this.defaultRowData = defaultRowData;
    }
}

class MessageFieldsFormTable extends FormTable {
    constructor(table, columns, ordered) {
        super(table, columns, ordered);

        this.setDefaultRowData({unit: '?', factor: '?', offset: '?', min: '?', max: '?'});
    }

    addRow(data, focus, index) {
        const tr = super.addRow(data, focus, index);

        const typeSelect = tr.children(':nth-child(1)').children('select');
        const sizeInBitsInput = tr.children(':nth-child(2)').children('input');
        const nameInput = tr.children(':nth-child(3)').children('input');
        const descriptionInput = tr.children(':nth-child(4)').children('input');
        const unitInput = tr.children(':nth-child(5)').children('input');
        const factorInput = tr.children(':nth-child(6)').children('input');
        const offsetInput = tr.children(':nth-child(7)').children('input');
        const minInput = tr.children(':nth-child(8)').children('input');
        const maxInput = tr.children(':nth-child(9)').children('input');

        this.updateRow(typeSelect, sizeInBitsInput, nameInput, descriptionInput, unitInput, factorInput, offsetInput, minInput, maxInput);
        typeSelect.change(() => {
            this.updateRow(typeSelect, sizeInBitsInput, nameInput, descriptionInput, unitInput, factorInput, offsetInput, minInput, maxInput);
        });
    }

    updateRow(typeSelect, sizeInBitsInput, nameInput, descriptionInput, unitInput, factorInput, offsetInput, minInput, maxInput) {
        const type = JSON.parse(typeSelect.val());

        if (type === 'bool') {
            // boolean
            sizeInBitsInput.val('1');
            unitInput.val('');
            factorInput.val('');
            offsetInput.val('');
            minInput.val('');
            maxInput.val('');

            $(sizeInBitsInput).prop('disabled', true);
            $(nameInput).prop('disabled', false);
            $(descriptionInput).prop('disabled', false);
            $(unitInput).prop('disabled', true);
            $(factorInput).prop('disabled', true);
            $(offsetInput).prop('disabled', true);
            $(minInput).prop('disabled', true);
            $(maxInput).prop('disabled', true);
        }
        else if (type === 'multiplex') {
            // multiplex
            unitInput.val('');
            factorInput.val('');
            offsetInput.val('');

            $(sizeInBitsInput).prop('disabled', false);
            $(nameInput).prop('disabled', false);
            $(descriptionInput).prop('disabled', false);
            $(unitInput).prop('disabled', true);
            $(factorInput).prop('disabled', true);
            $(offsetInput).prop('disabled', true);
            $(minInput).prop('disabled', false);
            $(maxInput).prop('disabled', false);
        }
        else if (type === 'reserved') {
            // reserved
            nameInput.val('');
            descriptionInput.val('');
            unitInput.val('');
            factorInput.val('');
            offsetInput.val('');
            minInput.val('');
            maxInput.val('');

            $(sizeInBitsInput).prop('disabled', false);
            $(nameInput).prop('disabled', true);
            $(descriptionInput).prop('disabled', true);
            $(unitInput).prop('disabled', true);
            $(factorInput).prop('disabled', true);
            $(offsetInput).prop('disabled', true);
            $(minInput).prop('disabled', true);
            $(maxInput).prop('disabled', true);
        }
        else if (!isNaN(type)) {
            // enum type
            unitInput.val('');
            factorInput.val('');
            offsetInput.val('');
            minInput.val('');
            maxInput.val('');

            $(sizeInBitsInput).prop('disabled', false);
            $(nameInput).prop('disabled', false);
            $(descriptionInput).prop('disabled', false);
            $(unitInput).prop('disabled', true);
            $(factorInput).prop('disabled', true);
            $(offsetInput).prop('disabled', true);
            $(minInput).prop('disabled', true);
            $(maxInput).prop('disabled', true);
        }
        else {
            // uint, int, float
            $(sizeInBitsInput).prop('disabled', false);
            $(nameInput).prop('disabled', false);
            $(descriptionInput).prop('disabled', false);
            $(unitInput).prop('disabled', false);
            $(factorInput).prop('disabled', false);
            $(offsetInput).prop('disabled', false);
            $(minInput).prop('disabled', false);
            $(maxInput).prop('disabled', false);
        }
    }
}

function can_id_redraw(hidden, frame_type_defs) {
    var parent = hidden.parent();
    var select = parent.children('select');
    parent.children().slice(2).remove();

    var value = parseInt(hidden.val(), 10);

    if (isNaN(value)) {
        value = 0;
    }

    const frame_type = select.val();

    if (frame_type === 'UNDEF') {
        // TODO: is this still necessary/relevant? back-end must force it to NULL in DB anyways
        hidden.val(0);
    }
    else if (frame_type_defs.hasOwnProperty(frame_type)) {
        // Custom frame ID formats (frame-id-formats.yml)

        const def = frame_type_defs[frame_type];
        const selects = [];

        // Iterate sub-fields of frame ID & generate UI
        for (const field of def.fields) {
            const select = $('<select>');

            // populate field options
            for (const [i, value] of field.options.entries()) {
                select.append($('<option>').text("(" + i.toString() + ") " + value).attr('value', i));
            }
            // set initial value
            select.val((value >> field.lsb) % Math.pow(2, field.bits));
            // add event listener & add to parent
            parent.append(select);

            selects.push(select);
        }

        // Re-calculation event handler
        const recalculate = () => {
            let value = 0;
            // assemble fields back together to compute the frame ID
            for (const [i, field] of def.fields.entries()) {
                value |= selects[i].val() << field.lsb;
            }
            hidden.val(value);
        };

        // Attach re-calculation event handler
        selects.forEach(select => select.on('change', recalculate));
    }
    else {
        // Hexadecimal input

        var id = $('<input type="text" pattern="[a-fA-F0-9]+">').val(value.toString(16));
        id.change(function() {
            let value = parseInt(id.val(), 16);

            if (isNaN(value)) {
                value = 0;
            }

            hidden.val(value.toString(10));
        });

        const input_group = $('<div class="input-group">');
        input_group.append('<div class="input-group-addon">0x</div>');
        input_group.append(id);
        parent.append(input_group);
    }
}

function can_id_init(hidden, frame_type_defs) {
    can_id_redraw(hidden, frame_type_defs);
    hidden.parent().children('select').change(function() { can_id_redraw(hidden, frame_type_defs); });
}
