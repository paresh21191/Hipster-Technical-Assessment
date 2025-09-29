<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    
    public function index()
    {
        
        $products = Product::with('primaryImage')->paginate(20);
        return view('products.index', compact('products'));
    }

    
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // max 10MB
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->withErrors(['csv_file' => 'Unable to open the CSV file']);
        }

        $header = fgetcsv($handle);
        $requiredColumns = ['sku', 'name', 'description', 'price'];
        $missingColumns = array_diff($requiredColumns, $header);

        $resultSummary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0,
        ];

        if (!empty($missingColumns)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'Missing required columns: ' . implode(', ', $missingColumns)]);
        }

        $skuIndex = array_search('sku', $header);
        $nameIndex = array_search('name', $header);
        $descIndex = array_search('description', $header);
        $priceIndex = array_search('price', $header);

        $seenSkus = [];
        $rows = [];

    
        while (($row = fgetcsv($handle)) !== false) {
            $resultSummary['total']++;
    
            if (count($row) < count($header)) {
                $resultSummary['invalid']++;
                continue;
            }

            $sku = trim($row[$skuIndex]);
            if (empty($sku)) {
                $resultSummary['invalid']++;
                continue;
            }

            if (in_array($sku, $seenSkus)) {
                $resultSummary['duplicates']++;
                continue;
            }

            $seenSkus[] = $sku;

            $rows[] = [
                'sku' => $sku,
                'name' => trim($row[$nameIndex]),
                'description' => trim($row[$descIndex]),
                'price' => is_numeric($row[$priceIndex]) ? floatval($row[$priceIndex]) : 0,
            ];
        }
        fclose($handle);

    
        DB::beginTransaction();
        try {
            foreach ($rows as $data) {
                $product = Product::where('sku', $data['sku'])->lockForUpdate()->first();
                if ($product) {
                    // Update
                    $product->update($data);
                    $resultSummary['updated']++;
                } else {
                    // Insert
                    Product::create($data);
                    $resultSummary['imported']++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['csv_file' => 'Database error: ' . $e->getMessage()]);
        }

        return back()->with('import_result', $resultSummary);
    }
}