function schemaToggleAggregateRating() {
    var toggle = $('input[name="data[header][product][aggregate_rating]"]:checked').val();

    $('.aggregate-rating-toggle').toggle((toggle == 1));
}

$(document).ready(function() {
    schemaToggleAggregateRating();

    $('input[name="data[header][product][aggregate_rating]"]').on('change', function() {
        schemaToggleAggregateRating();
    });
});
