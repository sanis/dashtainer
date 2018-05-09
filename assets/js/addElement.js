$(document).on('click', '[data-toggle="add-element"]', function(e) {
    e.stopPropagation();
    e.preventDefault && e.preventDefault();

    let $target = $(this.getAttribute('data-target'));

    $.ajax({
        url: this.getAttribute('href'),
        cache: false
    }).done(function(response) {
        response = Array.isArray(response) ? response : [response];

        for (let i = 0; i < response.length; i++) {
            parseResponse($target, $(response[i]));
        }

        DASHTAINER.eventDataToggleTab();
    });
});

function parseResponse($target, row) {
    let content = row[0]['data'];

    $target.append(content);
}
