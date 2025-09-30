<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products Bulk Import & Image Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #drop-zone {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            color: #aaa;
            margin-bottom: 20px;
        }
        #drop-zone.dragover {
            border-color: #333;
            color: #333;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="mb-4">Products Bulk CSV Import & Chunked Image Upload</h1>

    @if(session('import_result'))
        <div class="alert alert-info">
            <strong>Import Summary:</strong>
            <ul>
                <li>Total Rows: {{ session('import_result')['total'] }}</li>
                <li>Imported: {{ session('import_result')['imported'] }}</li>
                <li>Updated: {{ session('import_result')['updated'] }}</li>
                <li>Invalid: {{ session('import_result')['invalid'] }}</li>
                <li>Duplicates: {{ session('import_result')['duplicates'] }}</li>
            </ul>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
     
<div class="container mt-4">
    <div class="row">
        <!-- CSV Import Card -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Bulk CSV Import (Products)</div>
                <div class="card-body">
                    <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File (≥ 10,000 rows, columns: sku, name, description, price)</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" required accept=".csv, text/csv, text/plain">
                        </div>
                        <button type="submit" class="btn btn-primary">Import CSV</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Drag & Drop Upload Card -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Drag & Drop Image Upload (Chunked)</div>
                <div class="card-body">
                    <div id="drop-zone" class="mb-3 p-3 border border-secondary rounded text-center" style="cursor:pointer;">
                        Drag and drop images here or click to select files
                    </div>
                    <input type="file" id="file-input" multiple style="display:none">
                    <div id="upload-status" class="mb-3"></div>

                    <form id="attach-form">
                        @csrf
                        <div class="mb-3">
                            <label for="product_sku" class="form-label">Product SKU to attach image
                                <span style="color:red">*</span>
                            </label>
                            <input type="text" class="form-control" id="product_sku" name="product_sku" required placeholder="Enter product SKU">
                        </div>
                        <div class="mb-3">
                            <label for="upload_identifier" class="form-label">Upload Identifier (from upload)
                                <span style="color:red">*</span>
                            </label>
                            <input type="text" class="form-control" id="upload_identifier" name="upload_identifier" required placeholder="Upload Identifier">
                        </div>
                        <button type="submit" class="btn btn-success">Attach Primary Image to Product</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


    <h2>Products List (Paginated)</h2>
    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Price</th>
            <th>Primary Image</th>
        </tr>
        </thead>
        <tbody>
        @foreach($products as $product)
            <tr>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->name }}</td>
                <td>${{ number_format($product->price, 2) }}</td>
                <td>
                    @if($product->primaryImage)
                    <!-- <img src="{{ asset('storage/') }}" alt="Test Image" style="max-width: 100px;"> -->

                        <img src="{{ asset('storage/' . $product->primaryImage->path) }}" alt="Primary Image" style="max-width: 100px;">
                    @else
                        No image
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $products->links() }}
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function() {
    const dropZone = $('#drop-zone');
    const fileInput = $('#file-input');
    const uploadStatus = $('#upload-status');

    
    async function calculateSHA256(file) {
        if (window.crypto && window.crypto.subtle) {
            const arrayBuffer = await file.arrayBuffer();
            const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        } else {
            // Fallback or implement another method if needed
            return null;
        }
    }

    // Initialize upload on server
    function initUpload(file, uploadIdentifier, productSku, checksum) {
        return $.post("{{ route('uploads.init') }}", {
            _token: '{{ csrf_token() }}',
            upload_identifier: uploadIdentifier,
            filename: file.name,
            product_sku: productSku,
            checksum: checksum
        });
    }

    // Upload chunk base64 encoded
    function uploadChunk(uploadIdentifier, chunkIndex, chunkData) {
        return $.post("{{ route('uploads.chunk') }}", {
            _token: '{{ csrf_token() }}',
            upload_identifier: uploadIdentifier,
            chunk_index: chunkIndex,
            chunk_data: chunkData
        });
    }

    // Complete upload
    function completeUpload(uploadIdentifier, totalChunks, checksum) {
        return $.post("{{ route('uploads.complete') }}", {
            _token: '{{ csrf_token() }}',
            upload_identifier: uploadIdentifier,
            total_chunks: totalChunks,
            checksum: checksum
        });
    }

    // Handle file upload in chunks
    async function handleFileUpload(file, productSku) {
        const chunkSize = 1024 * 512; // 512 KB chunks
        const totalChunks = Math.ceil(file.size / chunkSize);
        const uploadIdentifier = productSku;

        uploadStatus.append(`<p>Starting upload for <strong>${file.name}</strong> (SKU: ${productSku})</p>`);

        const checksum = await calculateSHA256(file);
        if (!checksum) {
            uploadStatus.append('<p class="text-danger">Could not calculate checksum. Aborting upload.</p>');
            return;
        }

        // Init upload on server
        try {
            await initUpload(file, uploadIdentifier, productSku, checksum);
        } catch (err) {
            uploadStatus.append('<p class="text-danger">Failed to initialize upload: ' + err.responseText + '</p>');
            return;
        }

    
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(file.size, start + chunkSize);
            const chunkBlob = file.slice(start, end);

            // Convert chunk to base64 for transport
            const chunkBase64 = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = e => resolve(e.target.result.split(',')[1]); // base64 string
                reader.onerror = () => reject('Chunk read error');
                reader.readAsDataURL(chunkBlob);
            });

            try {
                await uploadChunk(uploadIdentifier, i, chunkBase64);
                uploadStatus.append(`<p>Uploaded chunk ${i + 1} / ${totalChunks} for ${file.name}</p>`);
            } catch (err) {
                uploadStatus.append('<p class="text-danger">Failed to upload chunk ' + i + ': ' + err.responseText + '</p>');
                return;
            }
        }

        // Complete upload
        try {
            await completeUpload(uploadIdentifier, totalChunks, checksum);
            uploadStatus.append(`<p class="text-success">Upload completed for <strong>${file.name}</strong>. Upload ID: <code>${uploadIdentifier}</code></p>`);
        } catch (err) {
            uploadStatus.append('<p class="text-danger">Upload completion failed: ' + err.responseText + '</p>');
        }
    }

    dropZone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.addClass('dragover');
    });

    dropZone.on('dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.removeClass('dragover');
    });

    dropZone.on('click', function() {
        fileInput.click();
    });

    dropZone.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        const productSku = $('#product_sku').val().trim();
        if (!productSku) {
            alert('Please enter the Product SKU before uploading images.');
            return;
        }
        for (let i = 0; i < files.length; i++) {
            handleFileUpload(files[i], productSku);
        }
    });

    fileInput.on('change', function() {
        const files = this.files;
        const productSku = $('#product_sku').val().trim();
        if (!productSku) {
            alert('Please enter the Product SKU before uploading images.');
            return;
        }
        for (let i = 0; i < files.length; i++) {
            handleFileUpload(files[i], productSku);
        }
        fileInput.val('');
    });

    // Attach primary image to product form
    $('#attach-form').on('submit', function(e) {
        e.preventDefault();
        const uploadIdentifier = $('#upload_identifier').val().trim();
        const productSku = $('#product_sku').val().trim();
        if (!uploadIdentifier || !productSku) {
            alert('Please fill both Upload Identifier and Product SKU.');
            return;
        }

        $.post("{{ route('uploads.attach') }}", {
            _token: '{{ csrf_token() }}',
            upload_identifier: uploadIdentifier,
            product_sku: productSku
        }).done(function(data) {
            alert(data.message);
            location.reload();
        }).fail(function(xhr) {
            alert('Error: ' + xhr.responseJSON.error || xhr.responseText);
        });
    });
});
</script>
</body>
</html>
