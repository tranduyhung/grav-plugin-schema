var productReviewSelector = 'input[type="radio"][name^="data[header][product][reviews]"]';
var productRatingSelector = 'input[name="data[header][product][aggregate_rating]"]';
var schemaTypeSelector = 'input[name="data[header][schema][type]"]';

function toggleSchemeType() {
    var schemaType = $(schemaTypeSelector + ':checked').val();

    if (schemaType == '') return;

    var els = $('.microdata-fieldset');

    for (var i = 0; i < els.length; i++) {
        var el = $(els[i]);

        if (el.hasClass(schemaType + 'Microdata')) {
            el.show();
        } else {
            el.hide();
        }
    }
}

function toggleProductAggregateRating() {
    var toggle = $(productRatingSelector + ':checked').val();

    $('.product-aggregate-rating-toggle').toggle((toggle == 1));
}

function toggleProductReviewRating(el) {
    var toggle = el.val();

    el.closest('.form-field').siblings('.review-toggle').toggle((toggle == 1));
}

$(document).ready(function() {
    toggleSchemeType();
    toggleProductAggregateRating();

    var productReviewToggles = $(productReviewSelector + ':checked');

    if (productReviewToggles.length > 0) {
        for (var i = 0; i < productReviewToggles.length; i++) {
            var el = productReviewToggles[i];
            toggleProductReviewRating($(el));
        }
    }

    $(schemaTypeSelector).on('change', function() {
        toggleSchemeType();
    });

    $(productRatingSelector).on('change', function() {
        toggleProductAggregateRating();
    });

    $('body').on('change', productReviewSelector, function(e) {
        toggleProductReviewRating($(e.currentTarget));
    });
});
