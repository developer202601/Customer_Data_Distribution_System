<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExcelCoordinator;
use App\Support\ProcessedDataset;
use App\Support\ProcessesExcelRows;
use App\Support\UploadProcessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ProcessFileController extends Controller
{
	use ProcessesExcelRows;

	public function __construct()
	{
		ini_set('memory_limit', '1024M');
		ini_set('max_execution_time', 300);
	}

	/**
	 * Return paginated filtered rows as JSON for AJAX pagination.
	 */
	public function rows(Request $request): JsonResponse
	{
		$dataset = $this->resolveDatasetOrRedirect(
			'Upload a file to review filtered rows.'
		);

		if ($dataset instanceof RedirectResponse) {
			return response()->json([
				'status' => 'missing',
				'message' => 'Processed dataset not available.',
			], 404);
		}

		$headers = $dataset->headers();
		$page = max(1, (int) $request->query('page', 1));
		$perPage = (int) $request->query('per_page', 100);
		$perPage = max(1, min(1000, $perPage));
		$vipApplied = $request->boolean('vip');
		$searchTerm = trim((string) $request->query('search', ''));

		$result = $dataset->paginateFilteredRows($page, $perPage, $vipApplied, $searchTerm);
		$total = (int) ($result['total'] ?? 0);

		$rowsOut = [];
		foreach ($result['rows'] ?? [] as $rowIndex => $row) {
			$entry = ['excel_row' => $rowIndex];
			foreach ($headers as $letter => $meta) {
				$entry[$letter] = $row[$letter] ?? '';
			}
			$rowsOut[] = $entry;
		}

		$headerList = [];
		foreach ($headers as $letter => $meta) {
			$headerList[] = ['key' => $letter, 'label' => $meta['label']];
		}

		$lastPage = $perPage > 0 ? (int) max(1, ceil($total / $perPage)) : 1;

		return response()->json([
			'status' => 'ok',
			'headers' => $headerList,
			'rows' => $rowsOut,
			'meta' => [
				'total' => $total,
				'per_page' => $perPage,
				'page' => $page,
				'last_page' => $lastPage,
			],
		]);
	}

	private const EXPECTED_COLUMNS = [
		'RUN_DATE',
		'REGION',
		'RTOM',
		'CUSTOMER_REF',
		'ACCOUNT_NUM',
		'PRODUCT_LABEL',
		'MEDIUM',
		'CUSTOMER_SEGMENT',
		'ADDRESS_NAME',
		'FULL_ADDRESS',
		'LATEST_BILL_MNY',
		'MOBILE_CONTACT_TEL',
		'EMAIL_ADDRESS',
		'CREDIT_SCORE',
		'CREDIT_CLASS_NAME',
		'BILL_HANDLING_CODE_NAME',
		'AGE_MONTHS',
		'SALES_PERSON',
		'ACCOUNT_MANAGER',
		'SLT_GL_SUB_SEGMENT',
		'BILLING_CENTRE',
		'PROVINCE',
		'NEXT_BILL_DTM',
		'BILL_MONTH',
		'LATEST_BILL_DTM',
		'INVOICING_CO_ID',
		'INVOICING_CO_NAME',
		'PRODUCT_SEQ',
		'PRODUCT_ID',
		'LATEST_PRODUCT_STATUS',
		'BILL_HANDLING_CODE',
		'SLT_BUSINESS_LINE_VALUE',
		'SALES_CHANNEL',
	];

	private const OPTIONAL_COLUMNS = [
		'ADDRESS_NAME',
		'EMAIL_ADDRESS',
		'CREDIT_SCORE',
		'SALES_PERSON',
		'SALES_CHANNEL',
	];

	private const FILTER_MEDIUM_VALUES = ['COPPER', 'FTTH'];
	private const FILTER_STATUS_VALUE = 'OK';
	private const FILTER_MIN_ARREARS = 2400;
	private const FILTER_INVOICING_CO_IDS = ['1'];
	private const PREVIEW_ROW_LIMIT = 6;

	public function create(): View
	{
		return view('process.upload');
	}

	public function store(Request $request): RedirectResponse|JsonResponse
	{
		$request->validate([
			'upload' => 'required|file|mimes:zip',
		]);

		$file = $request->file('upload');
		$token = (string) Str::uuid();
		$archivePath = $file->storeAs('uploads', $token . '.zip');
		$originalName = $file->getClientOriginalName();

		try {
			$storedPath = $this->extractWorkbookFromArchive($archivePath, $token);
		} catch (\RuntimeException $exception) {
			Storage::delete($archivePath);
			Storage::delete('uploads/' . $token . '.xlsx');

			throw ValidationException::withMessages([
				'upload' => $exception->getMessage(),
			]);
		}

		$this->initialiseProgressState($token, $storedPath, $originalName, $archivePath);

		ProcessExcelCoordinator::dispatch($token, $storedPath, $originalName);

		if ($request->expectsJson()) {
			return response()->json([
				'token' => $token,
			]);
		}

		return redirect()
			->route('process.upload.create')
			->with('status', 'Processing started. You can refresh this page in a moment to see the preview.');
	}

	public function cancel(Request $request): JsonResponse
	{
		$data = $request->validate([
			'token' => 'required|string',
			'reason' => 'nullable|string',
		]);

		$token = $data['token'];
		$state = Cache::get($this->progressCacheKey($token));

		if (! $state) {
			return response()->json([
				'status' => 'missing',
				'message' => 'Processing session already cleared.',
			], 404);
		}

		UploadProcessManager::cancel($token, $state['batch_id'] ?? null, $data['reason'] ?? null);

		return response()->json([
			'status' => 'cancelled',
		]);
	}

	public function progress(string $token): JsonResponse
	{
		$state = Cache::get($this->progressCacheKey($token));

		if (! $state) {
			return response()->json([
				'status' => 'missing',
				'progress' => 0,
				'message' => 'Processing state not found. Please upload the file again.',
			], 404);
		}

		return response()->json([
			'status' => $state['status'] ?? 'queued',
			'progress' => $state['progress'] ?? 0,
			'message' => $state['message'] ?? 'Preparing data…',
			'error' => $state['error'] ?? null,
			'processed_rows' => $state['processed_rows'] ?? null,
			'total_rows' => $state['total_rows'] ?? null,
			'chunks_total' => $state['chunks_total'] ?? null,
			'chunks_completed' => $state['chunks_completed'] ?? null,
		]);
	}

	public function complete(string $token): RedirectResponse
	{
		return $this->finalizeUpload($token);
	}

	public function preview(Request $request): View|RedirectResponse
	{
		return $this->renderFilteredView($request, false);
	}

	public function vip(Request $request): View|RedirectResponse
	{
		return $this->renderFilteredView($request, true);
	}

	public function exportVip(Request $request): StreamedResponse|RedirectResponse
	{
		$dataset = $this->prepareVipExportRows($request);

		if ($dataset instanceof RedirectResponse) {
			return $dataset;
		}

		$headers = $dataset->headers();
		$searchTerm = trim((string) $request->query('search', ''));

		$exportSpreadsheet = new Spreadsheet();
		$sheet = $exportSpreadsheet->getActiveSheet();

		$headerLabels = ['Excel Row'];
		foreach ($headers as $meta) {
			$headerLabels[] = $meta['label'];
		}

		$sheet->fromArray($headerLabels, null, 'A1');

		$rowPointer = 2;
		foreach ($dataset->filteredRowsMatching(true, $searchTerm) as $rowIndex => $columns) {
			$rowValues = [$rowIndex];
			foreach ($headers as $letter => $meta) {
				$rowValues[] = $columns[$letter] ?? '';
			}

			$sheet->fromArray($rowValues, null, 'A' . $rowPointer);
			$rowPointer++;
		}

		if ($rowPointer === 2) {
			$sheet->fromArray([], null, 'A2');
		}

		$downloadName = $this->buildVipFilename('xlsx');

		return response()->streamDownload(function () use ($exportSpreadsheet) {
			$writer = new Xlsx($exportSpreadsheet);
			$writer->save('php://output');
		}, $downloadName, [
			'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		]);
	}

	private function renderFilteredView(Request $request, bool $forceVip): View|RedirectResponse
	{
		$dataset = $this->resolveDatasetOrRedirect(
			$forceVip ? 'Upload a file to review VIP records.' : 'Upload a file to see the filtered results.'
		);

		if ($dataset instanceof RedirectResponse) {
			return $dataset;
		}

		$headers = $dataset->headers();
		$vipApplied = $forceVip || $request->boolean('vip');
		$searchTerm = trim((string) $request->query('search', ''));
		$searchApplied = $searchTerm !== '';

		$perPage = $vipApplied ? 100 : self::PREVIEW_ROW_LIMIT;
		if ($searchApplied) {
			$perPage = max($perPage, 100);
		}

		$result = $dataset->paginateFilteredRows(1, $perPage, $vipApplied, $searchTerm);
		$displayRows = $result['rows'] ?? [];
		$matchingCount = (int) ($result['total'] ?? 0);
		$limited = $matchingCount > count($displayRows);

		if (! $searchApplied && ! $vipApplied) {
			$matchingCount = $dataset->filteredRowCount();
			$limited = $matchingCount > self::PREVIEW_ROW_LIMIT;
		}

		if (! $searchApplied && $vipApplied) {
			$matchingCount = $dataset->vipRowCount();
			$limited = $matchingCount > count($displayRows);
		}

		$summary = [
			'total_rows' => $dataset->sourceRowCount(),
			'filtered_rows' => $dataset->filteredRowCount(),
			'skipped_rows' => max($dataset->sourceRowCount() - $dataset->filteredRowCount(), 0),
		];

		return view('process.filtered', [
			'headers' => $headers,
			'filteredRows' => $displayRows,
			'summary' => $summary,
			'filename' => $dataset->originalFilename(),
			'searchTerm' => $searchTerm,
			'searchApplied' => $searchApplied,
			'filteredCount' => $matchingCount,
			'limited' => $limited,
			'vipApplied' => $vipApplied,
			'displayCount' => count($displayRows),
		]);
	}

	private function resolveDatasetOrRedirect(string $missingUploadStatus): ProcessedDataset|RedirectResponse
	{
		try {
			$dataset = ProcessedDataset::fromSession();
		} catch (Throwable $exception) {
			$dataset = null;
		}

		if (! $dataset) {
			session()->forget(['process.upload.dataset', 'process.upload.data', 'process.upload.path']);
			return redirect()
				->route('process.upload.create')
				->with('status', $missingUploadStatus);
		}

		return $dataset;
	}

	private function prepareVipExportRows(Request $request): ProcessedDataset|RedirectResponse
	{
		return $this->resolveDatasetOrRedirect(
			'Upload a file to export VIP records.'
		);
	}

	private function buildVipFilename(string $extension): string
	{
		$base = 'vip-records';

		try {
			$dataset = ProcessedDataset::fromSession();
		} catch (Throwable $exception) {
			$dataset = null;
		}

		if ($dataset && ($original = $dataset->originalFilename())) {
			$baseCandidate = pathinfo($original, PATHINFO_FILENAME);
			if (is_string($baseCandidate) && $baseCandidate !== '') {
				$base = $baseCandidate;
			}
		}

		return $base . '_vip.' . $extension;
	}

	private function initialiseProgressState(string $token, string $storedPath, string $originalName, ?string $archivePath = null): void
	{
		Cache::put($this->progressCacheKey($token), [
			'status' => 'queued',
			'progress' => 0,
			'message' => 'Waiting for an available worker…',
			'uploaded_path' => $storedPath,
			'archive_path' => $archivePath,
			'original_filename' => $originalName,
			'processed_rows' => 0,
			'total_rows' => null,
			'chunks_total' => 0,
			'chunks_completed' => 0,
			'batch_id' => null,
		], now()->addMinutes(60));
	}

	private function progressCacheKey(string $token): string
	{
		return 'process:upload:' . $token;
	}

	private function finalizeUpload(string $token): RedirectResponse
	{
		$state = Cache::get($this->progressCacheKey($token));

		if (! $state) {
			return redirect()->route('process.upload.create')->withErrors([
				'upload' => 'Processing session expired. Please upload the file again.',
			]);
		}

		if (($state['status'] ?? null) !== 'complete') {
			$message = $state['message'] ?? 'Processing not finished yet. Please wait a moment.';

			if (($state['status'] ?? null) === 'failed' && isset($state['error'])) {
				$message = $state['error'];
			}

			return redirect()->route('process.upload.create')->withErrors([
				'upload' => $message,
			]);
		}

		$datasetPath = $state['dataset_path'] ?? null;

		if (! $datasetPath || ! Storage::exists($datasetPath)) {
			Cache::forget($this->progressCacheKey($token));

			return redirect()->route('process.upload.create')->withErrors([
				'upload' => 'Processed data could not be located. Please upload the file again.',
			]);
		}

		$payload = json_decode(Storage::get($datasetPath), true);
		$manifest = null;

		if (is_array($payload)) {
			$manifestCandidate = $payload['manifest'] ?? $payload;
			if (is_array($manifestCandidate) && ! empty($manifestCandidate['headers'])) {
				$manifest = $manifestCandidate;
			}
		}

		if (! $manifest) {
			Cache::forget($this->progressCacheKey($token));
			return redirect()->route('process.upload.create')->withErrors([
				'upload' => 'Unable to load processed data. Please try again.',
			]);
		}

		session()->put('process.upload.dataset', [
			'token' => $token,
			'manifest_path' => $datasetPath,
		]);
		session()->forget('process.assignments');
		session()->forget('process.upload.data');
		session()->forget('process.upload.path');

		Cache::forget($this->progressCacheKey($token));

		return redirect()->route('process.exclusions.create')
			->with('status', 'File processed successfully. Continue by uploading exclusion files.');
	}

	private function extractWorkbookFromArchive(string $archivePath, string $token): string
	{
		$absoluteArchivePath = Storage::path($archivePath);
		$zip = new ZipArchive();

		if ($zip->open($absoluteArchivePath) !== true) {
			throw new \RuntimeException('Unable to open the uploaded ZIP file. Please try again.');
		}

		try {
			$entryName = $this->locateExcelEntry($zip);
			$targetRelativePath = 'uploads/' . $token . '.xlsx';
			$targetAbsolutePath = Storage::path($targetRelativePath);
			$targetDirectory = dirname($targetAbsolutePath);

			if (! is_dir($targetDirectory)) {
				mkdir($targetDirectory, 0755, true);
			}

			$sourceStream = $zip->getStream($entryName);
			if (! $sourceStream) {
				throw new \RuntimeException('Unable to read the Excel workbook inside the ZIP file.');
			}

			$targetHandle = fopen($targetAbsolutePath, 'wb');
			if (! $targetHandle) {
				fclose($sourceStream);
				throw new \RuntimeException('Unable to store the extracted Excel workbook.');
			}

			stream_copy_to_stream($sourceStream, $targetHandle);
			fclose($sourceStream);
			fclose($targetHandle);

			return $targetRelativePath;
		} finally {
			$zip->close();
		}
	}

	private function locateExcelEntry(ZipArchive $zip): string
	{
		$entries = [];

		for ($index = 0; $index < $zip->numFiles; $index++) {
			$stat = $zip->statIndex($index);
			$name = $stat['name'] ?? '';

			if ($name === '' || str_ends_with($name, '/')) {
				continue;
			}

			if (str_ends_with(strtolower($name), '.xlsx')) {
				$entries[] = $name;
			}
		}

		if (empty($entries)) {
			throw new \RuntimeException('The ZIP file must contain exactly one Excel (.xlsx) workbook. None were found.');
		}

		if (count($entries) > 1) {
			throw new \RuntimeException('The ZIP file must contain exactly one Excel (.xlsx) workbook.');
		}

		return $entries[0];
	}
}
