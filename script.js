
// =====================================
// GOOD TOGETHER - SEARCH FUNCTION
// =====================================

// Find the search field.
const search = document.getElementById("search");

// Find the city dropdown.
const city = document.getElementById("city");

// Find the category dropdown.
const category = document.getElementById("category");

// Find the reset button.
const resetButton = document.getElementById("resetButton");

// Find all event cards.
const events = document.querySelectorAll(".event");

// Find the event counter and message.
const eventCount = document.getElementById("eventCount");
const noResults = document.getElementById("noResults");


// FUNCTION: Search for matching events.
function searchEvents() {

    // Get the values entered by the user.
    const keyword = search.value.toLowerCase();
    const selectedCity = city.value;
    const selectedCategory = category.value;

    // Count how many events are displayed.
    let count = 0;

    // Check each event one at a time.
    events.forEach(function(event) {

        // Read the event information.
        const eventText = event.textContent.toLowerCase();
        const eventCity = event.dataset.city;
        const eventCategory = event.dataset.category;

        // Check the search conditions.
        const matchKeyword = eventText.includes(keyword);

        const matchCity =
            selectedCity === "all" ||
            selectedCity === eventCity;

        const matchCategory =
            selectedCategory === "all" ||
            selectedCategory === eventCategory;

        // Show the event if all conditions match.
        if (matchKeyword && matchCity && matchCategory) {

            event.hidden = false;
            count++;

        } else {

            // Hide events that do not match.
            event.hidden = true;

        }
    });

    // Show the number of matching events.
    eventCount.textContent = count + " event(s) found";

    // Display a message if no events match.
    if (count === 0) {
        noResults.hidden = false;
    } else {
        noResults.hidden = true;
    }
}


// Run search when user types.
search.addEventListener("input", searchEvents);

// Run search when user changes city.
city.addEventListener("change", searchEvents);

// Run search when user changes category.
category.addEventListener("change", searchEvents);


// RESET BUTTON
resetButton.addEventListener("click", function() {

    // Clear all search options.
    search.value = "";
    city.value = "all";
    category.value = "all";

    // Display all events again.
    searchEvents();
});


// Display all events when the page first loads.
searchEvents();
