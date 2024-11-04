<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>REA | Buy Electricity</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet"/>
    <!-- Scripts -->
{{--    @vite(['resources/css/app.css', 'resources/js/app.js'])--}}
    <link rel="stylesheet" href="{{ asset('build/assets/app-bfe8e78d.css') }}">
    <script src="{{ asset('build/assets/app-b1941ff8.js') }}"></script>
    <script src="{{ asset('shuffle/main.js') }}"></script>
    <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>


</head>

<body class="antialiased">
<section class="bg-gray-50 min-h-screen flex flex-row items-center">
    <div class="w-full flex flex-wrap h-full">
        <div class="w-full lg:w-5/12 px-5">
            <div class="py-12 md:pt-8 h-full">
                <div class="flex flex-col h-full max-w-xs mx-auto">
                    <div class="lg:h-40"></div>
                    <img class="block w-64 mb-10" src="{{ asset('shuffle/logos/rea_logo.png')}}" alt="">


                    <a class="inline-flex mt-auto items-center text-sm text-white hover:text-yellowGreen-600 font-semibold"
                       href="#">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.5 5L7.5 10L12.5 15" stroke="currentColor" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        <span class="ml-2">Back</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="w-full lg:w-7/12 px-5">
            <div class="py-12 md:pt-8 md:px-8">
                <div class="max-w-sm lg:max-w-md xl:max-w-lg mx-auto lg:mr-0 xl:mx-auto">
                    <div class="flex items-center justify-end mb-20">
                        <a class="inline-flex mr-10 items-center leading-loose text-white text-sm font-semibold hover:text-yellowGreen-600"
                           href="#">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g clip-path="url(#clip0_3478_15610)">
                                    <path
                                        d="M10.0001 18.3333C14.6025 18.3333 18.3334 14.6023 18.3334 9.99996C18.3334 5.39759 14.6025 1.66663 10.0001 1.66663C5.39771 1.66663 1.66675 5.39759 1.66675 9.99996C1.66675 14.6023 5.39771 18.3333 10.0001 18.3333Z"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                    <path d="M2.08325 10.4166L6.66658 12.0833L5.83325 15L6.66658 17.5"
                                          stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"></path>
                                    <path
                                        d="M14.1667 17.0833L13.7501 15L11.6667 14.1666V11.25L14.1667 10.4166L17.9167 10.8333"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                    <path
                                        d="M15.8333 4.58337L15.4167 5.83337L12.5 6.25004V8.75004L14.5833 7.91671H16.25L17.9167 8.75004"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                    <path d="M2.08325 8.75L4.16659 7.08333L6.24992 6.66667L7.91659 4.16667L7.08325 2.5"
                                          stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"></path>
                                </g>
                            </svg>
                            <span class="ml-2">English</span>
                        </a>

                    </div>
                    <div class="max-w-sm">
                        <div id="lottie-animation" class="hidden">
                            <dotlottie-player
                                src="https://lottie.host/4dd29940-655e-4e5b-89b2-68ce68f7b97f/FocVTMGDJm.json"
                                background="transparent" speed="1" style="width: 150px; height: 150px;" loop
                                autoplay></dotlottie-player>
                        </div>
                        <div id="error" class="hidden">
                            <div
                                class="font-bold text-red-600 text-lg text-center mb-10 rounded-lg border-2 border-dotted border-red-800 p-10">
                                You have entered a wong Meter Number
                            </div>
                            <button onclick="goBack()"
                                    class="xs:flex-shrink-0 group relative w-full h-12 flex items-center justify-center px-4 p-px font-bold text-gray-900 bg-[#B8723E] rounded-lg transition-all duration-300 focus:outline-none">
                                <div
                                    class="absolute top-0 left-0 w-full h-full rounded-lg ring ring-yellowGreen-900 animate-pulse group-hover:ring-0 transition duration-300"></div>
                                <span>Try Again</span>
                            </button>
                        </div>
                        <div id="success" class="hidden">
                            <div
                                class="font-bold text-green-600 text-lg text-center mb-10 rounded-lg border-2 border-dotted border-green-800 p-10">
                                Complete the transaction on your Mobile Money phone
                            </div>
                            <button onclick="goBack()"
                                    class="xs:flex-shrink-0 group relative w-full h-12 flex items-center justify-center px-4 p-px font-bold text-gray-900 bg-[#B8723E] rounded-lg transition-all duration-300 focus:outline-none">
                                <div
                                    class="absolute top-0 left-0 w-full h-full rounded-lg ring ring-yellowGreen-900 animate-pulse group-hover:ring-0 transition duration-300"></div>
                                <span>Go Back</span>
                            </button>
                        </div>
                        <form id="first_form" action="" class="">
                            <div class="mb-8">
                                <label class="block mb-2 text-sm font-medium text-gray-800" for="">Enter your meter
                                    number</label>
                                <input id="meterNo"
                                       class="py-2 px-4 h-11 w-full placeholder-gray-500 text-[#231F20] border border-gray-700 focus:border-teal-800 outline-none ring ring-transparent focus:ring-teal-800 rounded-lg"
                                       type="text" placeholder="SM16R-04-XXXXXXXX">
                            </div>

                            <button
                                class="xs:flex-shrink-0 group relative w-full h-12 flex items-center justify-center px-4 p-px font-bold text-gray-900 bg-[#B8723E] rounded-lg transition-all duration-300 focus:outline-none"
                                type="submit">
                                <div
                                    class="absolute top-0 left-0 w-full h-full rounded-lg ring ring-yellowGreen-900 animate-pulse group-hover:ring-0 transition duration-300"></div>
                                <span>Search</span>
                            </button>

                            <div class="flex items-center justify-center">

                            </div>
                        </form>

                        <form id="second_form" action="" class="hidden">
                            <div class="bg-indigo-900 bg-opacity-10 rounded-lg border-2 border-indigo-900 px-10 py-5">
                                <div class="block mb-2 text-md font-bold text-gray-800" for="">Customer Name: <span
                                        id="customer_name" class="font-bold text-md"></span></div>
                                <div class="block mb-2 text-md font-bold text-gray-800" for="">Meter No: <span
                                        id="meter_no" class="font-bold text-md"></span></div>
                            </div>
                            <div class="mb-8">
                                <hr/>
                                <div class="h-6"></div>
                                <label class="block mb-2 text-sm font-medium text-gray-800" for="">Enter Mobile Money
                                    Number </label>
                                <input id="phone_number" name="phone_number"
                                       class="py-2 px-4 h-11 w-full placeholder-gray-500 text-[#231F20] border border-gray-700 focus:border-teal-800 outline-none ring ring-transparent focus:ring-teal-800 rounded-lg"
                                       type="tel" maxlength="10" placeholder="0975123456">
                            </div>

                            <div class="mb-8">
                                <label class="block mb-2 text-sm font-medium text-gray-800" for="">Enter Amount</label>
                                <input id="amount" name="amount"
                                       class="py-2 px-4 h-11 w-full placeholder-gray-500 text-[#231F20] border border-gray-700 focus:border-teal-800 outline-none ring ring-transparent focus:ring-teal-800 rounded-lg"
                                       type="number" maxlength="10" placeholder="100">
                            </div>

                            <button
                                class="xs:flex-shrink-0 group relative w-full h-12 flex items-center justify-center px-4 p-px font-bold text-gray-900 bg-[#B8723E] rounded-lg transition-all duration-300 focus:outline-none"
                                type="submit">
                                <div
                                    class="absolute top-0 left-0 w-full h-full rounded-lg ring ring-yellowGreen-900 animate-pulse group-hover:ring-0 transition duration-300"></div>
                                <span>Pay Now</span>
                            </button>

                            <div class="flex items-center justify-center">

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    var fetched_customer_id = "1";
    var fetched_meter_no = "1";

    function goBack() {
        const errorDiv = document.getElementById('error');
        const successDiv = document.getElementById('success');
        const first_form = document.getElementById("first_form");
        errorDiv.classList.add("hidden");
        successDiv.classList.add("hidden");
        first_form.classList.remove("hidden");
    }

    document.getElementById('first_form').addEventListener('submit', function (event) {
        // Prevent the form from submitting by default
        event.preventDefault();

        // Select the div element where you want to display the animation
        const animationContainer = document.getElementById('lottie-animation');
        const errorDiv = document.getElementById('error');
        const first_form = document.getElementById("first_form");
        animationContainer.classList.remove("hidden");
        first_form.classList.add("hidden");


        var entered_meter_number = document.getElementById('meterNo').value;
        // Data to be sent in the POST request as JSON
        const postData = {
            meter_no: entered_meter_number,
        };

        // API endpoint URL
        const apiUrl = '/api/client_name';

        // Make a POST request with JSON body
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(postData)
        })
            .then(response => {
                if (response.status === 200) {
                    // Handle success (status code 200)
                    return response.json();
                } else if (response.status === 400) {
                    // Handle client error (status code 400)
                    animationContainer.classList.add('hidden');
                    errorDiv.classList.remove("hidden")
                    throw new Error('Bad request');
                } else {
                    // Handle other status codes
                    animationContainer.classList.add('hidden');
                    errorDiv.classList.remove("hidden")
                    throw new Error('Unexpected error');
                }
            })
            .then(data => {
                if (data.customer) {
                    const {
                        name,
                        id,
                        phone_number,
                        meter_no
                    } = data.customer;

                    //save fetched customer_id
                    fetched_customer_id = id;
                    fetched_meter_no = meter_no;

                    //hide first_form and review second form

                    const second_form = document.getElementById("second_form");
                    animationContainer.classList.add('hidden');
                    second_form.classList.remove('hidden');

                    //fill the second form with meter number and customer name
                    const meter_number_span = document.getElementById("meter_no");
                    const customer_name_span = document.getElementById("customer_name");

                    meter_number_span.textContent = meter_no;
                    customer_name_span.textContent = name;


                    console.log('Name:', name);
                    console.log('Id:', id);
                    console.log('Phone Number:', phone_number);
                    console.log('Meter Number:', meter_no);
                    console.log('Customer Id', fetched_customer_id);

                } else {
                    const {
                        message
                    } = data;
                    console.log(message);
                }
            })
            .catch(error => {
                // Handle errors
                console.error('Error:', error);
            });
    });

    document.getElementById("second_form").addEventListener("submit", function (event) {
        // Prevent the form from submitting by default
        event.preventDefault();

        const animationContainer = document.getElementById('lottie-animation');
        const second_form = document.getElementById("second_form");
        second_form.classList.add('hidden');
        animationContainer.classList.remove('hidden');

        const successDiv = document.getElementById("success");

        const entered_phone_number = document.getElementById('phone_number').value;
        const entered_amount = document.getElementById('amount').value;

        const postData = {
            customer_id: fetched_customer_id,
            meter_no: fetched_meter_no,
            phone_number: entered_phone_number,
            amount: entered_amount
        };

        // API endpoint URL
        const apiUrl = '/api/pay';

        // Make a POST request with JSON body
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(postData)
        })
            .then(response => {
                if (response.status === 200) {
                    // Handle success (status code 200)
                    animationContainer.classList.add('hidden');
                    successDiv.classList.remove("hidden")
                    return response.json();
                } else if (response.status === 400) {
                    console.log(response);
                    // Handle client error (status code 400)
                    animationContainer.classList.add('hidden');
                    successDiv.classList.remove("hidden")

                    throw new Error('Bad request');
                } else {
                    console.log(response);
                    // Handle other status codes
                    animationContainer.classList.add('hidden');
                    successDiv.classList.remove("hidden")
                    throw new Error('Unexpected error');
                }
            })
            .then(data => {
                console.log(data);
            })
            .catch(error => {
                // Handle errors
                console.error('Error:', error);
            });


    })
</script>
</body>

</html>
