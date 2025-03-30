$(document).ready(function () {
    $("#PetSearch").on("input", function () {
        let query = $(this).val().trim();
        let suggestionsBox = $("#PetSuggestions");

        if (query.length === 0) {
            suggestionsBox.hide();
            return;
        }

        $.ajax({
            url: "../src/fetch_pets.php",
            type: "GET",
            data: { query: query },
            dataType: "json",
            success: function (pets) {
                suggestionsBox.empty();
                if (pets.length > 0) {
                    pets.forEach(pet => {
                        let item = $("<div>")
                            .addClass("suggestion-item")
                            .text(`${pet.pet_name} (Owner: ${pet.owner_name})`)
                            .data("pet-id", pet.PetId)
                            .click(function () {
                                $("#PetSearch").val(pet.pet_name);
                                $("#PetId").val(pet.PetId);
                                suggestionsBox.hide();
                            });
                        suggestionsBox.append(item);
                    });
                } else {
                    suggestionsBox.append('<div class="no-result">No Pet Found</div>');
                }
                suggestionsBox.show();
            }
        });
    });

    $(document).on("click", function (event) {
        if (!$(event.target).closest("#PetSearch, #PetSuggestions").length) {
            $("#PetSuggestions").hide();
        }
    });
});