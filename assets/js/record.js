document.addEventListener("DOMContentLoaded", function () {
    applyDropdownAndLinksFunctionality(); // Dropdowns & Row Clicks
    attachGeneralEventListeners(); // Confirmation Dialogs & Actions
    attachSearchAndFilterListeners(); // Search & Filter Listeners
});

// ===============================
// 📂 Dropdown & Links Functionality
// ===============================
function applyDropdownAndLinksFunctionality() {
    document.addEventListener("click", function (event) {
        const target = event.target;

        // Handle dropdown button clicks
        if (target.matches(".three-dot-btn, .three-dot-btns")) {
            event.stopPropagation();
            const dropdown = target.nextElementSibling;

            // Close other dropdowns
            document.querySelectorAll(".dropdown-menu, .dropdown-menus").forEach(menu => {
                if (menu !== dropdown) menu.style.display = "none";
            });

            // Toggle the current dropdown
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        } 
        // Close all dropdowns if clicking outside
        else {
            document.querySelectorAll(".dropdown-menu, .dropdown-menus").forEach(menu => {
                menu.style.display = "none";
            });
        }
    });

    // Toggle pet details when clicking a row
    document.querySelectorAll(".pet-row").forEach(row => {
        row.addEventListener("click", () => togglePetDetails(row));
    });

    // Hover card event delegation
    document.querySelectorAll(".hover-container").forEach(container => {
        const icon = container.querySelector(".fa-info-circle");
        const hoverCard = container.querySelector(".hover-card");

        if (!icon || !hoverCard) return;

        icon.addEventListener("mouseenter", () => hoverCard.style.display = "block");
        icon.addEventListener("mouseleave", () => hoverCard.style.display = "none");
        hoverCard.addEventListener("mouseenter", () => hoverCard.style.display = "block");
        hoverCard.addEventListener("mouseleave", () => hoverCard.style.display = "none");

        icon.addEventListener("click", (event) => {
            event.stopPropagation();
            hoverCard.style.display = hoverCard.style.display === "block" ? "none" : "block";
        });
    });
}

// 📄 Expand/Collapse Pet Details
function togglePetDetails(row) {
    const detailsRow = row.nextElementSibling;
    detailsRow.style.display = detailsRow.style.display === "none" ? "table-row" : "none";
}

// ===============================
// 🛠 Search & Filter Functionality
// ===============================
function attachSearchAndFilterListeners() {
    const searchInput = document.getElementById("searchInput");
    const serviceFilter = document.getElementById("serviceFilter");
    const staffList = document.getElementById("staffList");

    async function fetchResults() {
        const query = new URLSearchParams({
            search: searchInput.value.trim(),
            service: serviceFilter.value,
            ajax: "1"
        });

        try {
            const response = await fetch(`record.php?${query.toString()}`);
            const newContent = await response.text();
            staffList.innerHTML = newContent;
            applyDropdownAndLinksFunctionality(); // Reapply dropdown listeners
        } catch (err) {
            console.error("Error fetching results:", err);
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    searchInput.addEventListener("input", debounce(fetchResults, 300));
    serviceFilter.addEventListener("change", fetchResults);
}

// ===============================
// ⚠️ Confirmation Dialogs (Swal)
// ===============================
function attachGeneralEventListeners() {
    window.confirmConfine = function (petId) {
        Swal.fire({
            title: 'Confirm Confinement',
            text: 'Are you sure you want to confine this pet?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Confine',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("../src/confined_pets.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `petId=${petId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire("Confined!", "The pet has been successfully confined.", "success");
                        document.getElementById(`pet-row-${petId}`)?.remove();
                    } else {
                        Swal.fire("Error!", data.error || "Failed to confine pet.", "error");
                    }
                })
                .catch(() => Swal.fire("Error!", "Something went wrong. Please try again.", "error"));
            }
        });
    };
    window.confirmArchive = function (petId) {
        Swal.fire({
            title: 'Confirm Archive',
            text: 'Are you sure you want to archive this pet? This action can be undone later.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Archive',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`../src/archive_pets.php?pet_id=${encodeURIComponent(petId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire("Archived!", "The pet has been successfully archived.", "success").then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Error!", data.error || "An error occurred while archiving the pet.", "error");
                        }
                    })
                    .catch(() => Swal.fire("Error!", "An error occurred. Please try again.", "error"));
            }
        });
    };
}

// ===============================
// 🔄 Handle AJAX Table Updates
// ===============================
function updateTableContent(url) {
    const staffList = document.getElementById("staffList");
    staffList.innerHTML = `<tr><td colspan="6" style="text-align:center;"><div class="spinner"></div></td></tr>`;

    fetch(url)
        .then(response => response.text())
        .then(html => {
            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = html;
            staffList.innerHTML = tempDiv.querySelector("#staffList").innerHTML;
            applyDropdownAndLinksFunctionality(); // Reapply dropdown listeners
        })
        .catch(error => console.error("Error fetching data:", error));
}

// ===============================
// 📄 Search Input Listener
// ===============================
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    let searchTimeout;

    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            const query = searchInput.value.trim();
            updateTableContent(query.length > 0 ? `record.php?search=${encodeURIComponent(query)}` : `record.php`);
        }, 300);
    });
});