const ARTIST_AUTOCOMPLETE_URL = 'artist.php?action=autocomplete';
const TAGS_AUTOCOMPLETE_URL = 'torrents.php?action=autocomplete_tags';
const SELECTOR = '[data-gazelle-autocomplete="true"]';
$(document).ready(initAutocomplete);

function initAutocomplete () {
    if (!$.Autocomplete) {
        window.setTimeout(function () {
            initAutocomplete();
        }, 500);
        return;
    }

    const url = {
        path: window.location.pathname.split('/').reverse()[0].split('.')[0],
        query: window.location.search.slice(1).split('&').reduce((a, b) => Object.assign(a, { [b.split('=')[0]]: b.split('=')[1] }), {})
    };

    $('#artistsearch' + SELECTOR).autocomplete({
        deferRequestBy: 300,
        onSelect: function (suggestion) {
            window.location = 'artist.php?id=' + suggestion['data'];
        },
        serviceUrl: ARTIST_AUTOCOMPLETE_URL
    });

    if (url.path == 'torrents' || url.path == 'upload' || url.path == 'artist' || (url.path == 'requests' && url.query['action'] == 'new') || url.path == 'collages') {
        $('#artist' + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $('#artistsimilar' + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
    }
    if (url.path == 'torrents' || url.path == 'upload' || url.path == 'collages' || url.path == 'requests' || url.path == 'top10' || (url.path == 'requests' && url.query['action'] == 'new')) {
        $('#tags' + SELECTOR).autocomplete({
            deferRequestBy: 300,
            delimiter: ',',
            serviceUrl: TAGS_AUTOCOMPLETE_URL
        });
        $('#tagname' + SELECTOR).autocomplete({
            deferRequestBy: 300,
            delimiter: ',',
            serviceUrl: TAGS_AUTOCOMPLETE_URL
        });
    }

};
