document.addEventListener("DOMContentLoaded", function () {
  applyDropdownAndLinksFunctionality();
  attachGeneralEventListeners();
  initializeThreeDotMenu();
});

// ===============================
// 📂 Dropdown & Links Functionality
// ===============================
function applyDropdownAndLinksFunctionality() {
  document.addEventListener("click", function (event) {
    const target = event.target;

    if (target.matches(".three-dot-btn, .three-dot-btns")) {
      event.stopPropagation();
      const dropdown = target.nextElementSibling;

      document
        .querySelectorAll(".dropdown-menu, .dropdown-menus")
        .forEach((menu) => {
          if (menu !== dropdown) menu.style.display = "none";
        });

      dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
    } else {
      document
        .querySelectorAll(".dropdown-menu, .dropdown-menus")
        .forEach((menu) => {
          menu.style.display = "none";
        });
    }
  });

  document.querySelectorAll(".pet-row").forEach((row) => {
    row.addEventListener("click", () => togglePetDetails(row));
  });
}

// 📄 Expand/Collapse Pet Details
function togglePetDetails(row) {
  const detailsRow = row.nextElementSibling;
  detailsRow.style.display =
    detailsRow.style.display === "none" ? "table-row" : "none";
}

// ===============================
// 🔄 Handle AJAX Table Updates
// ===============================
function updateTableContent(url) {
  const staffList = document.getElementById("staffList");
  staffList.innerHTML = `<tr><td colspan="6" style="text-align:center;"><div class="spinner"></div></td></tr>`;

  fetch(url)
    .then((response) => response.text())
    .then((html) => {
      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = html;
      staffList.innerHTML = tempDiv.querySelector("#staffList").innerHTML;
      applyDropdownAndLinksFunctionality();
      initializeThreeDotMenu();
    })
    .catch((error) => console.error("Error fetching data:", error));
}

// ===============================
// 📄 Search Input Listener
// ===============================
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("searchInput");
    if (!searchInput) return;

    let searchTimeout;

    searchInput.addEventListener("input", function () {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            const query = searchInput.value.trim();
            fetchSearchResults(query);
        }, 300);
    });
});

function fetchSearchResults(query) {
    const staffList = document.getElementById("staffList");
    staffList.innerHTML = `<tr><td colspan="6" style="text-align:center;"><div class="spinner"></div></td></tr>`;

    fetch(`record.php?search=${encodeURIComponent(query)}`)
        .then(response => response.text())
        .then(html => {
            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = html;
            const newTableContent = tempDiv.querySelector("#staffList");
            if (newTableContent) {
                staffList.innerHTML = newTableContent.innerHTML;
            } else {
                staffList.innerHTML = "<tr><td colspan='6'>No records found.</td></tr>";
            }
            applyDropdownAndLinksFunctionality();
        })
        .catch(error => console.error("Error fetching search results:", error));
}

// ===============================
// 📌 Filter Actions
// ===============================
document.addEventListener("DOMContentLoaded", function () {
  document
    .querySelector(".apply-btn")
    .addEventListener("click", function (event) {
      event.preventDefault();
      loadFilteredPets();
    });

  document.querySelector(".clear-btn").addEventListener("click", function () {
    resetFilters();
  });
});

function loadFilteredPets() {
  const formData = new FormData(document.querySelector(".filter-container"));
  const queryString = new URLSearchParams(formData).toString();

  fetch(`../src/filter_pets.php?${queryString}`)
    .then((response) => response.json())
    .then((data) => {
      const tableBody = document.getElementById("staffList");
      tableBody.innerHTML = "";

      if (data.length === 0) {
        tableBody.innerHTML = "<tr><td colspan='6'>No pets found.</td></tr>";
        return;
      }

      data.forEach((pet) => {
        const row = `<tr class="pet-row">
                    <td>
                        <div class="hover-container">
                            ${pet.PetCode ?? "No information"}
                            <i class="fas fa-info-circle"></i>
                            <div class="hover-card">
                                <div class="profile-info">
                                    <img src="../assets/images/Icons/Profile User.png" class="profile-img">
                                    <div><strong>${pet.OwnerName}</strong></div>
                                </div>
                                <hr>
                                <div><strong>Email:</strong><br> ${
                                  pet.Email
                                }</div>
                                <div><strong>Phone Number:</strong><br> ${
                                  pet.Phone
                                }</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="three-dot-menu">
                            <button class="three-dot-btns">⋮</button>
                            <div class="dropdown-menus">
                                <a href="#" onclick="confirmConfine('${
                                  pet.PetId
                                }')">Confine Pet</a>
                                <a href="add_vaccine.php?pet_id=${
                                  pet.PetId
                                }">Add Vaccination</a>
                                <a href="#" onclick="confirmArchive('${
                                  pet.PetId
                                }'); return false;">Archive Pet</a>
                                <a href="#" onclick="confirmDelete('${
                                  pet.PetId
                                }'); return false;">Delete</a>
                            </div>
                        </div>
                        <a href="pet_profile.php?pet_id=${
                          pet.PetId
                        }" class="pet-profile-link">${
          pet.PetName ?? "No Name"
        }</a>
                    </td>
                    <td>${pet.PetType ?? "No information"}</td>
                    <td>${pet.PetStatus ?? "Unknown"}</td>
                    <td>${pet.LastVisit ?? "No past visit"}</td>
                    <td>${pet.NextVisit ?? "No upcoming visit"}</td>
                </tr>`;
        tableBody.innerHTML += row;
      });

      initializeThreeDotMenu();
    })
    .catch((error) => console.error("Error fetching pets:", error));
}

function resetFilters() {
  document.querySelector(".filter-container").reset();
  loadFilteredPets();
}

// ===============================
// ⚠️ Confirmation Dialogs (Swal)
// ===============================
function attachGeneralEventListeners() {
  window.confirmConfine = function (petId) {
    Swal.fire({
      title: "Confirm Confinement",
      text: "Are you sure you want to confine this pet?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, Confine",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        fetch("../src/confined_pets.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `petId=${petId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              Swal.fire(
                "Confined!",
                "The pet has been successfully confined.",
                "success"
              );
              document.getElementById(`pet-row-${petId}`)?.remove();
            } else {
              Swal.fire(
                "Error!",
                data.error || "Failed to confine pet.",
                "error"
              );
            }
          })
          .catch(() =>
            Swal.fire(
              "Error!",
              "Something went wrong. Please try again.",
              "error"
            )
          );
      }
    });
  };
}

// ===============================
// 📌 Initialize Three-Dot Menus
// ===============================
function initializeThreeDotMenu() {
    document.querySelectorAll(".three-dot-btns").forEach(button => {
        button.onclick = function (event) {
            event.stopPropagation();
            closeOtherDropdowns();
            const menu = this.nextElementSibling;

            if (!menu) return;
            menu.style.display = menu.style.display === "block" ? "none" : "block";
        };
    });

    document.addEventListener("click", function () {
        closeOtherDropdowns();
    });
}

function closeOtherDropdowns() {
    document.querySelectorAll(".dropdown-menus").forEach(menu => {
        menu.style.display = "none";
    });
}
