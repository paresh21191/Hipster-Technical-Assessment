<!DOCTYPE html>
<html>
<head>
    <title>Discount Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">

    <div class="max-w-lg mx-auto bg-white shadow-lg rounded-lg p-6">
        <h1 class="text-xl font-bold mb-4">Discount Demo</h1>

        <form method="POST" action="{{ route('demo.apply') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium">Select User</label>
                <select name="user_id" class="w-full border rounded p-2">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} (ID: {{ $user->id }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Amount</label>
                <input type="number" name="amount" class="w-full border rounded p-2" value="100">
            </div>

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Apply Discount</button>
        </form>

        @if(session('final'))
            <div class="mt-6 p-4 bg-green-100 rounded">
                <p><strong>User:</strong> {{ session('user') }}</p>
                <p><strong>Original Amount:</strong> {{ session('original') }}</p>
                <p><strong>Final Amount:</strong> {{ session('final') }}</p>
            </div>
        @endif
    </div>

</body>
</html>
