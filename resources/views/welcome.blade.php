<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased">
    <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-dots-darker bg-center bg-gray-100 dark:bg-dots-lighter dark:bg-gray-900 selection:bg-red-500 selection:text-white">

        <div class="max-w-7xl mx-auto p-6 lg:p-8 w-full">

            @error('file')
            <div class="bg-red-50 border border-red-300 text-red-600 px-5 py-3 rounded relative mb-3" role="alert">
                <strong class="font-bold">{{ $message }}</strong>
                <span class="absolute top-0 bottom-0 right-0 px-5 py-3">
                    <svg class="alert_close fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z" />
                    </svg>
                </span>
            </div>
            @enderror

            @if(session()->has('success-msg'))
            <div class="bg-green-50 border border-green-400 text-green-600 px-5 py-3 rounded relative mb-3" role="alert">
                <strong class="font-bold">{{ session('success-msg') }}</strong>
                <span class="absolute top-0 bottom-0 right-0 px-5 py-3">
                    <svg class="alert_close fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z" />
                    </svg>
                </span>
            </div>
            @endif

            @if(session()->has('error-msg'))
            <div class="bg-red-50 border border-red-300 text-red-600 px-5 py-3 rounded relative mb-3" role="alert">
                <strong class="font-bold">{{ session('error-msg') }}</strong>
                <span class="absolute top-0 bottom-0 right-0 px-5 py-3">
                    <svg class="alert_close fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z" />
                    </svg>
                </span>
            </div>
            @endif


            <div class="flex justify-center">
                <form action="{{ route('files.upload') }}" method="post" enctype="multipart/form-data" class="w-full p-6 bg-white dark:bg-gray-800/50 dark:bg-gradient-to-bl from-gray-700/50 via-transparent dark:ring-1 dark:ring-inset dark:ring-white/5 rounded-lg shadow-2xl shadow-gray-500/20 dark:shadow-none flex justify-between">
                    @csrf
                    <div class="upload-container">
                        <input type="file" name="file" id="csv_input" accept=".xlsx, .xls, .csv" required />
                        <figure>
                            <img src="{{ asset('img/upload-icon.png') }}" alt="upload">
                        </figure>
                        <p class="text-gray-500 dark:text-gray-400">Click or drop file here</p>
                    </div>

                    <div class="h-16 bg-red-500 flex text-white justify-center rounded-full self-center ml-4">
                        <button type="submit" class="px-6 font-semibold">Upload</button>
                    </div>
                </form>
            </div>


            <div class="mt-16">
                <div class="grid grid-cols-1 gap-6 lg:gap-8">

                    <div class="scale-100 p-6 bg-white dark:bg-gray-800/50 dark:bg-gradient-to-bl from-gray-700/50 via-transparent dark:ring-1 dark:ring-inset dark:ring-white/5 rounded-lg shadow-2xl shadow-gray-500/20 dark:shadow-none flex motion-safe:hover:scale-[1.01] transition-all duration-250 focus:outline focus:outline-2 focus:outline-red-500">

                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Time</th>
                                        <th scope="col">File Name</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($files as $file)
                                    <tr>
                                        <td>
                                            {{ \Carbon\Carbon::parse($file->created_at)->format('Y-m-d g:i A') }} <br>
                                            {{ $file->created_at->diffForHumans() }}
                                        </td>
                                        <td>{{ $file->name_og }}</td>
                                        <td id="job-id-{{ $file->job_batch_id }}">{{ $file->status->label() }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td class="text-center" colspan="3">No files!</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

            <div class="flex justify-center mt-16 px-0 sm:items-center sm:justify-between">
                <div class="text-center text-sm text-gray-500 dark:text-gray-400 sm:text-left">
                    <div class="flex items-center gap-4">
                        <a href="https://tharhtoo.netlify.app/" target="_blank" class="group inline-flex items-center hover:text-gray-700 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" class="-mt-px mr-1 w-5 h-5 stroke-gray-400 dark:stroke-gray-600 group-hover:stroke-gray-600 dark:group-hover:stroke-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                            THARHTOO
                        </a>
                    </div>
                </div>

                <div class="ml-4 text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right sm:ml-0">
                    Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
                </div>
            </div>
        </div>
    </div>
</body>

<script src="{{ Vite::asset('resources/js/app.js') }}"></script>

</html>