document.addEventListener("DOMContentLoaded", function () {

    console.log("Handicraft by Sonia Slider Loaded Successfully!");

    const slides = document.querySelectorAll(".slide");
    const dots = document.querySelectorAll(".dot");

    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");

    let currentSlide = 0;

    let slideInterval;


    /* ===============================
       SHOW SLIDE
    =============================== */

    function showSlide(index) {

        if (slides.length === 0) {
            console.log("No slides found!");
            return;
        }


        /* Remove active from all slides */

        slides.forEach(function (slide) {
            slide.classList.remove("active");
        });


        /* Remove active from all dots */

        dots.forEach(function (dot) {
            dot.classList.remove("active");
        });


        /* Correct index */

        if (index >= slides.length) {
            currentSlide = 0;
        }

        else if (index < 0) {
            currentSlide = slides.length - 1;
        }

        else {
            currentSlide = index;
        }


        /* Activate current slide */

        slides[currentSlide].classList.add("active");


        /* Activate current dot */

        if (dots[currentSlide]) {
            dots[currentSlide].classList.add("active");
        }

    }


    /* ===============================
       NEXT SLIDE
    =============================== */

    function nextSlide() {

        showSlide(currentSlide + 1);

    }


    /* ===============================
       PREVIOUS SLIDE
    =============================== */

    function previousSlide() {

        showSlide(currentSlide - 1);

    }


    /* ===============================
       NEXT BUTTON
    =============================== */

    if (nextBtn) {

        nextBtn.addEventListener("click", function () {

            nextSlide();

            restartAutoSlide();

        });

    }


    /* ===============================
       PREVIOUS BUTTON
    =============================== */

    if (prevBtn) {

        prevBtn.addEventListener("click", function () {

            previousSlide();

            restartAutoSlide();

        });

    }


    /* ===============================
       DOT BUTTONS
    =============================== */

    dots.forEach(function (dot, index) {

        dot.addEventListener("click", function () {

            showSlide(index);

            restartAutoSlide();

        });

    });


    /* ===============================
       AUTO SLIDE
    =============================== */

    function startAutoSlide() {

        slideInterval = setInterval(function () {

            nextSlide();

        }, 3500);

    }


    /* ===============================
       RESTART AUTO SLIDE
    =============================== */

    function restartAutoSlide() {

        clearInterval(slideInterval);

        startAutoSlide();

    }


    /* ===============================
       START
    =============================== */

    showSlide(0);

    startAutoSlide();


    console.log("Total slides:", slides.length);

});