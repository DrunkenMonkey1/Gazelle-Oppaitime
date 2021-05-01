let buttonCount;
const MAX_EXTRAS = 5;
const FORMATS = ['MP3', 'FLAC', 'AAC', 'AC3', 'DTS'];
const BITRATES = ['192', 'APS (VBR)', 'V2 (VBR)', 'V1 (VBR)', '256', 'APX (VBR)', 'V0 (VBR)', '320', 'Lossless', '24bit Lossless'];

function initMultiButtons () {
    if (!$('#add_format')) {
        return;
    }
    buttonCount = 1;
    $('#add_format').click(function () {
        createRow();
    });

    $('#remove_format').click(function () {
        removeRow();
    });
}

function createRow () {
    if (buttonCount >= 1) {
        $('#remove_format').show();
    }
    if (buttonCount == MAX_EXTRAS) {
        $('#add_format').hide();
    }
    const after = buttonCount > 1 ? '#extra_format_row_' + (buttonCount - 1) : '#placeholder_row_top';
    const master = $(document.createElement('tr')).attr({
        id: 'extra_format_row_' + buttonCount
    }).insertAfter(after);

    $(document.createElement('td')).addClass('label').html('Extra format ' + buttonCount + ':').appendTo(master);
    const row = $(document.createElement('td')).appendTo(master);
    addFile(row);
    addFormats(row);
    addBitrates(row);
    addReleaseDescription(row);
    $('#post').val('Upload torrents');
    buttonCount++;
}

function addFile (row) {
    const id = buttonCount;
    $(document.createElement('input')).attr({
        id: 'extra_file_' + buttonCount,
        type: 'file',
        name: 'extra_file_' + buttonCount,
        size: '30'
    }).appendTo(row);

}

function addFormats (row) {
    $(document.createElement('span')).html('&nbsp;&nbsp;&nbsp;&nbsp;Format: ').appendTo(row);
    $(document.createElement('select')).attr({
        id: 'format_' + buttonCount,
        name: 'extra_format[]'
    }).html(createDropDownOptions(FORMATS)).appendTo(row);
}

function addBitrates (row) {
    $(document.createElement('span')).html('&nbsp;&nbsp;&nbsp;&nbsp;Bitrate: ').appendTo(row);
    $(document.createElement('select')).attr({
        id: 'bitrate_' + buttonCount,
        name: 'extra_bitrate[]'
    }).html(createDropDownOptions(BITRATES)).appendTo(row);
    /*change(
     function () {
     const id = $(this).attr('id');
     if ($(this).val() == 'Other') {
     $(this).after(
     '<span id="other_bitrate_span_' + id
     + '" class=""> <input type="text" name="extra_other_bitrate[]" size="5" id="other_bitrate_' + id
     + '"><input type="checkbox" id="vbr_' + id + '" name="extra_vbr[]"><label for="vbr_' + id
     + '"> (VBR)</label> </span>');
     } else {
     $("#other_bitrate_span_" + id).remove();
     }
     });*/
}

function addReleaseDescription (row) {
    const id = buttonCount;
    const desc_row = $(document.createElement('tr')).attr({ id: 'desc_row' }).css('cursor', 'pointer').appendTo(row);
    $(document.createElement('a')).html('&nbsp;&nbsp;[Add Release Description]').css('marginLeft', '-5px').appendTo(desc_row).click(function () {
        $('#extra_release_desc_' + id).toggle(300);
    });
    $(document.createElement('textarea')).attr({
        id: 'extra_release_desc_' + id,
        name: 'extra_release_desc[]',
        cols: 60,
        rows: 4,
        style: 'display:none; margin-left: 5px; margin-top: 10px; margin-bottom: 10px;'
    }).appendTo(desc_row);
}

function createDropDownOptions (array) {
    s = '<option value=\'0\'>---</option>';
    for (const i in array) {
        s += ('<option value="' + array[i] + '">' + array[i] + '</option>');
    }
    return s;
}

function removeRow () {
    if (buttonCount > 1) {
        $('#placeholder_row_bottom').prev().remove();
        $('#add_format').show();
        buttonCount--;
        $('#post').val('Upload torrents');
    }
    if (buttonCount == 1) {
        $('#remove_format').hide();
        $('#post').val('Upload torrent');
    }

}

$(document).ready(initMultiButtons);
