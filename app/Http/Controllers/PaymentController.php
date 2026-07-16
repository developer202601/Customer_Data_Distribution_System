<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MasterDatasetRow;
use OpenSpout\Reader\Common\Creator\ReaderEntityFactory;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{

public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv',
    ]);

    $reader = ReaderEntityFactory::createReaderFromFile(
        $request->file('file')->getRealPath()
    );

    $reader->open($request->file('file')->getRealPath());

    $header = [];
    $batchSize = 1000;
    $count = 0;

    DB::beginTransaction();

    try {

        foreach ($reader->getSheetIterator() as $sheet) {

            foreach ($sheet->getRowIterator() as $row) {

                $cells = $row->toArray();

                // First row = header
                if (empty($header)) {
                    $header = $cells;
                    continue;
                }

                $data = array_combine($header, $cells);

                Customer::where('customer_no', $data['customer_no'])
                    ->update([
                        'phone' => $data['phone'],
                        'email' => $data['email'],
                    ]);

                $count++;

                if ($count % $batchSize == 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }
        }

        DB::commit();

    } catch (\Exception $e) {

        DB::rollBack();
        $reader->close();

        return back()->withErrors([
            'file' => $e->getMessage()
        ]);
    }

    $reader->close();

    return back()->with('success', "$count records updated.");
}
}
