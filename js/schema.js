function schemaToggleProductAggregateRating() {
    var toggle = $('input[name="data[header][product][aggregate_rating]"]:checked').val();

    $('.product-aggregate-rating-toggle').toggle((toggle == 1));
}

function schemaToggleProductReviewRating(el) {
    var toggle = el.val();

    el.closest('.form-field').siblings('.review-toggle').toggle((toggle == 1));
}

$(document).ready(function() {
    schemaToggleProductAggregateRating();

    var productReviewToggles = $('input[type="radio"][name^="data[header][product][reviews]"]:checked');

    if (productReviewToggles.length > 0) {
        for (var i = 0; i < productReviewToggles.length; i++) {
            var el = productReviewToggles[i];
            console.log(el);
            schemaToggleProductReviewRating($(el));
        }
    }

    $('input[name="data[header][product][aggregate_rating]"]').on('change', function() {
        schemaToggleProductAggregateRating();
    });

    $('body').on('change', 'input[type="radio"][name^="data[header][product][reviews]"]', function(e) {
        schemaToggleProductReviewRating($(e.currentTarget));
    });

});
