<!DOCTYPE html>
<html lang="en">

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pawsitive</title>
  <link rel="icon" type="image/x-icon" href="assets/images/logo/LOGO.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>ca
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>

<body class="bg-white overflow-x-hidden">
<header class="bg-[#156f77] py-5 px-6 md:px-20 lg:px-36 fixed w-full top-0 z-50 rounded-b-2xl">
    <nav class="flex justify-between items-center">
      <!-- Responsive Logo -->
      <div>
        <img src="assets/images/logo/LOGO 2 WHITE.png" 
             alt="Pawsitive Logo" 
             class="w-32 md:w-48 lg:w-64 xl:w-72">
      </div>

      <!-- Responsive Login Button -->
      <ul class="flex gap-2 md:gap-4 lg:gap-6 items-center">
  <li>
    <a href="#" onclick="openLoginModal()" 
       class="bg-white text-[#156f77] font-bold text-sm md:text-base lg:text-lg px-3 md:px-5 lg:px-6 py-1 md:py-2 
              rounded-full border-2 border-[#156f77] 
              ease-out transform 
              hover:scale-105  hover:bg-[#156f77] hover:text-white">
      Login
    </a>
  </li>
</ul>

 <!--
     Responsive Menu
      <div class="md:hidden">
        <button class="text-white focus:outline-none">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16m-7 6h7"></path>
          </svg>
        </button>
      </div> -->
    </nav>
  </header> 

  <main class="pt-32 md:pt-40 px-8 md:px-20">
    <section class="flex flex-col md:flex-row justify-center items-center text-left gap-6 md:gap-10">
      <div class="text-center md:text-left">
        <h1 class="text-4xl md:text-6xl font-bold text-black">Your Pet's Care</h1>
        <h2 class="text-4xl md:text-6xl font-bold text-[#156f77]">Our Priority</h2>
        <p class="text-base md:text-lg text-black mt-4 max-w-[42ch] leading-tight">
          We're dedicated to enhancing the lives of pets through expert care and heartfelt commitment.</p>
        <br>
      </div>
      <div class="relative translate-x-0 md:translate-x-0">
      <img src="assets/images/Icons/For Index.png" alt="Dog and Cat" class="w-80 md:w-[500px] max-w-full">
      </div>
    </section>

    <section class="bg-gray-100 py-10 md:py-20 mt-10 md:mt-20 px-8 md:px-20 rounded-lg">
      <div class="max-w-5xl mx-auto text-center">
        <h2 class="text-2xl md:text-3xl font-bold mb-5">About Us</h2>
        <p class="text-sm md:text-base">
        At PAWSITIVE, we believe that every pet deserves the best care and attention. Established with a passion for
            animal
            welfare, we aim to provide top-notch tech service to ensure your furry friends lead healthy and happy lives.
          </p>
          <br>
          <p class="text-sm md:text-base">
            Our team of aspiring developers with the help of Pet Adventure staff are committed to offering compassionate
            care
            tailored to the unique needs of every pet. Whether it's a routine check-up, specialized treatment,
            or quality pet supplies, we're here to support you every step of the way.
          </p>
          <div class="flex flex-col md:flex-row gap-6 md:gap-10 mt-6 md:mt-10">
          <div class="bg-[#156f77] p-6 rounded-lg shadow-lg text-white flex-1 text-center transition hover:bg-[#a8ebf0] hover:shadow-2xl hover:shadow-[#156f77]/40 hover:drop-shadow-lg hover:-translate-y-2  hover:text-[#156f77]">
           <h3 class="text-xl md:text-2xl font-bold">Mission</h3>
           <p><br>To empower pet owners and veterinary professionals through innovative technology,
            fostering healthier and happier lives for pets while strengthening the bond between humans and their
            animal companions.</p>
          </div>
          
          <div class="bg-[#156f77] p-6 rounded-lg shadow-lg text-white flex-1 text-center transition hover:bg-[#a8ebf0] hover:shadow-2xl hover:shadow-[#156f77]/40 hover:drop-shadow-lg hover:-translate-y-2 hover:text-[#156f77]">
            <h3 class="text-xl md:text-2xl font-bold">Vision</h3>
            <p><br>Our vision is to be the leading platform for seamless pet care management, and setting the standard for
              compassionate wellness solutions.
              We aim to empower pet owners and veterinary professionals to ensure every pet lives a healthy life.</p>
          </div> 

        </div>
      </div>
    </section>
  </main>
  <br>
  <div class="bg-white px-8 md:px-20">
    <img src="assets/images/Icons/Pet pic 4.png" alt="Group of Pets" class="w-full md:w-3/5 mx-auto">
  </div>

  <footer class="bg-black text-white py-10 px-8 md:px-20">
    <div class="flex flex-col md:flex-row justify-between items-start gap-10">
      <div>
        <img src="assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo" class="w-32 md:w-48">
        <ul class="mt-4 space-y-2">
          <li class="flex items-center">
            <a href="https://www.facebook.com/petadventure14" target="_blank" 
               class="flex items-center hover:underline">
              <img src="assets/images/Icons/Facebook.png" class="w-6 mr-2"> petadventure14
            </a>
          </li>
          <li class="flex items-center">
            <a href="mailto:petadventure20@gmail.com" 
               class="flex items-center hover:underline">
              <img src="assets/images/Icons/Email.png" class="w-6 mr-2"> petadventure20@gmail.com
            </a>
          </li>
          <li class="flex items-center">
            <a href="tel:09235283253" 
               class="flex items-center hover:underline">
              <img src="assets/images/Icons/Contact Number.png" class="w-6 mr-2"> 0923 528 3253
            </a>
          </li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg">Company</h4>
        <ul class="mt-2 space-y-2">
          <li><a href="#" class="hover:underline">Home</a></li>
          <li><a href="#" class="hover:underline">Appointment</a></li>
          <li><a href="#" class="hover:underline">Contacts</a></li>
          <li><a href="#" class="hover:underline">About</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg">Help Center</h4>
        <ul class="mt-2 space-y-2">
          <li><a href="#" class="hover:underline">Call Center</a></li>
          <li><a href="#" class="hover:underline">FAQs</a></li>
          <li><a href="#" class="hover:underline">Support Docs</a></li>
          <li><a href="#" class="hover:underline">Careers</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-lg">Address</h4>
        <p>Stall 1, Atilano Bldg, National Road, Cay Pomba, Sta. Maria, Bulacan, 3022</p>
      </div>
    </div>
    <p class="text-center mt-6 md:mt-10 text-sm">&copy; 2025 Pet Adventure Veterinary Services and Supplies, All Rights Reserved.</p>
</footer>

<!-- JavaScript for Modal -->
<script>
  function openLoginModal() {
    document.getElementById("loginModal").classList.remove("hidden");
  }
  function closeLoginModal() {
    document.getElementById("loginModal").classList.add("hidden");
  }
</script>

<script>
function openLoginModal() {
  Swal.fire({
    title: "<span class='custom-title' style='color: black;'>Select Login Type</span>",  // Title in black
    showCancelButton: false,
    showCloseButton: true,
    allowOutsideClick: true,
    showConfirmButton: false,  // Removes the OK button
    html: `
      <div class="flex flex-col space-y-4 font-poppins">
        <button onclick="window.location.href='public/owner_login.php'"
          class="w-full bg-[#156f77] text-white text-lg font-semibold py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg hover:-translate-y-1">
          Pet Owner Login
        </button>
        <button onclick="window.location.href='public/staff_login.php'"
          class="w-full bg-[#156f77] text-white text-lg font-semibold py-3 rounded-lg transition duration-200 shadow-md hover:shadow-lg hover:-translate-y-1">
          Clinic Staff Login
        </button>
      </div>
    `,
    customClass: {
      popup: "rounded-lg p-6",
      closeButton: "custom-close-button"
    },
    didOpen: () => {
      // Apply Poppins font to all elements
      document.querySelector(".swal2-popup").style.fontFamily = "Poppins, sans-serif";
      
      // Close button hover effect
      document.querySelector(".custom-close-button").style.color = "black";
      document.querySelector(".custom-close-button").addEventListener("mouseover", function() {
        this.style.color = "#156f77";
      });
      document.querySelector(".custom-close-button").addEventListener("mouseout", function() {
        this.style.color = "black";
      });
    }
  });
}
</script>

</body>
</html>

