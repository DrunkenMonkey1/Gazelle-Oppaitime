(function () {
    let ids = Array();
    $(document).ready(function () {
        $('input[id^=check_all]').click(function () {
            // Check or uncheck all requests
            const checked = ($(this).attr('checked') == 'checked');
            $('input[id^=multi]').each(function () {
                $(this).attr('checked', checked);
                let id = $(this).data('id');
                if (checked && $.inArray(id, ids) == -1) {
                    ids.push(id);
                } else if (!checked && $.inArray(id, ids) != -1) {
                    ids = $.grep(ids, function (value) {
                        return value != id;
                    });
                }
            });
        });
        $('input[id^=multi]').click(function () {
            // Put the ID in the array if checked, or removed if unchecked
            const checked = ($(this).attr('checked') == 'checked');
            let id = $(this).data('id');
            if (checked && $.inArray(id, ids) == -1) {
                ids.push(id);
            } else if (!checked && $.inArray(id, ids) != -1) {
                ids = $.grep(ids, function (value) {
                    return value != id;
                });
            }
        });
        $('input[id^=outcome]').click(function () {
            if ($(this).val() != 'Discard' && !confirm('Are you sure you wish to do this? This cannot be undone!')) {
                return false;
            }
            const id = $(this).data('id');
            if (id !== undefined) {
                // Only resolving one row
                resolveIDs = [id];
                const comment = $('input[id^=comment' + id + ']').val();
            } else {
                resolveIDs = ids;
                comment = '';
            }

            $.ajax({
                type: 'GET',
                dataType: 'json',
                url: 'tools.php?action=ajax_take_enable_request',
                data: {
                    'ids': resolveIDs,
                    'comment': comment,
                    'status': $(this).val(),
                    'type': 'resolve'
                }
            }).done(function (response) {
                if (response['status'] == 'success') {
                    for (let i = 0; i < resolveIDs.length; i++) {
                        $('#row_' + resolveIDs[i]).remove();
                    }
                } else {
                    alert(response['status']);
                }
            });
        });
        $('a[id^=unresolve]').click(function () {
            const id = $(this).data('id');
            if (id !== undefined) {
                $.ajax({
                    type: 'GET',
                    dataType: 'json',
                    url: 'tools.php?action=ajax_take_enable_request',
                    data: {
                        'id': id,
                        'type': 'unresolve'
                    }
                }).done(function (response) {
                    if (response['status'] == 'success') {
                        $('#row_' + id).remove();
                        alert('The request has been un-resolved. Please refresh your browser to see it.');
                    } else {
                        alert(response['status']);
                    }
                });
            }
        });
    });
})();

function ChangeDateSearch (rangeconstiable, dateTwoID) {
    const fullID = '#' + dateTwoID;
    if (rangeconstiable === 'between') {
        $(fullID).show();
    } else {
        $(fullID).hide();
    }
}
