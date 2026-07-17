<?php
/**
 * WIT Solapur Alumni Portal Backend in PHP
 * This file replaces backend/app.py and provides identical API endpoints
 * and behavior using a JSON file database (alumni_db.json).
 */

// Enable CORS for all routes (equivalent to Flask-CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Passcode, Authorization");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
define('UPLOAD_DIR', __DIR__ . '/alumni_images');
define('DB_FILE', __DIR__ . '/alumni_db.json');
define('ADMIN_PASSCODE', getenv('ADMIN_PASSCODE') ?: 'admin123');
define('ALLOWED_EXTENSIONS', ['png', 'jpg', 'jpeg', 'gif', 'webp']);

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Ensure database file exists
if (!file_exists(DB_FILE)) {
    file_put_contents(DB_FILE, json_encode([], JSON_PRETTY_PRINT));
}

// Parse request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Helper: Respond with JSON and exit
function json_response($data, $status_code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper: Check admin passcode
function verify_admin() {
    $passcode = isset($_SERVER['HTTP_X_ADMIN_PASSCODE']) ? $_SERVER['HTTP_X_ADMIN_PASSCODE'] : null;
    if (!$passcode || $passcode !== ADMIN_PASSCODE) {
        json_response(['success' => false, 'message' => 'Unauthorized admin passcode.'], 401);
    }
}

// Helper: Generate UUID v4
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Helper: Read database with shared lock
function db_get_all() {
    $db_handle = @fopen(DB_FILE, 'r');
    if (!$db_handle) {
        return [];
    }
    flock($db_handle, LOCK_SH);
    $size = filesize(DB_FILE);
    $content = $size > 0 ? fread($db_handle, $size) : '';
    flock($db_handle, LOCK_UN);
    fclose($db_handle);
    return json_decode($content, true) ?: [];
}

// --- Route Dispatcher ---

// 1. Fallback Static Image Serving (similar to serve_image in Flask)
if (preg_match('#/alumni_images/([^/]+)$#', $path, $matches)) {
    $filename = $matches[1];
    $filepath = UPLOAD_DIR . '/' . $filename;
    if (file_exists($filepath)) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $content_types = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $content_type = isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
        header("Content-Type: $content_type");
        readfile($filepath);
        exit;
    } else {
        json_response(['success' => false, 'message' => 'Image not found.'], 404);
    }
}

// 2. Public: Register Alumni (POST /api/alumni/register)
if ($method === 'POST' && preg_match('#/api/alumni/register$#', $path)) {
    // Retrieve form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $mobile_number = isset($_POST['mobile_number']) ? trim($_POST['mobile_number']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $graduation_year = isset($_POST['graduation_year']) ? trim($_POST['graduation_year']) : '';
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $company = isset($_POST['company']) ? trim($_POST['company']) : '';
    $image_file = isset($_FILES['image']) ? $_FILES['image'] : null;

    // Basic Validation
    if (empty($name) || empty($email) || empty($mobile_number) || empty($department) || empty($graduation_year) || empty($designation) || empty($company) || !$image_file || $image_file['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'All fields (including mobile number and image) are required.'], 400);
    }

    $filename = $image_file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        json_response(['success' => false, 'message' => 'Invalid image format. Allowed formats: PNG, JPG, JPEG, GIF, WEBP.'], 400);
    }

    // Save image locally with unique filename
    $uuid = generate_uuid();
    $uuid_hex = str_replace('-', '', $uuid);
    $unique_filename = $uuid_hex . '.' . $ext;
    $image_path = UPLOAD_DIR . '/' . $unique_filename;

    if (!move_uploaded_file($image_file['tmp_name'], $image_path)) {
        json_response(['success' => false, 'message' => 'Failed to save uploaded image.'], 500);
    }

    // Construct server image URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $api_pos = strpos($path, '/api/');
    if ($api_pos !== false) {
        $base_path = substr($path, 0, $api_pos);
    } else {
        $base_path = dirname($path);
    }
    $base_path = str_replace('/index.php', '', $base_path);
    $base_path = rtrim($base_path, '/');
    $image_url = $protocol . $host . $base_path . '/alumni_images/' . $unique_filename;

    // Prepare alumni object
    $alumni_data = [
        'id' => $uuid,
        'name' => $name,
        'email' => $email,
        'mobile_number' => $mobile_number,
        'department' => $department,
        'graduation_year' => $graduation_year,
        'designation' => $designation,
        'company' => $company,
        'status' => 'Pending',
        'created_at' => date("Y-m-d H:i:s"),
        'image_url' => $image_url,
    ];

    // Save to JSON database under exclusive lock
    $db_handle = fopen(DB_FILE, 'c+');
    if ($db_handle) {
        flock($db_handle, LOCK_EX);
        $size = filesize(DB_FILE);
        $records = [];
        if ($size > 0) {
            $content = fread($db_handle, $size);
            $records = json_decode($content, true) ?: [];
        }
        $records[] = $alumni_data;
        ftruncate($db_handle, 0);
        rewind($db_handle);
        fwrite($db_handle, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($db_handle);
        flock($db_handle, LOCK_UN);
        fclose($db_handle);

        json_response([
            'success' => true,
            'message' => 'Registration submitted successfully! It will appear in the directory once verified by the administrator.'
        ], 201);
    } else {
        // Cleanup uploaded image on failure
        if (file_exists($image_path)) {
            unlink($image_path);
        }
        json_response(['success' => false, 'message' => 'Failed to save registration data to database.'], 500);
    }
}

// 3. Public: Fetch Approved Alumni (GET /api/alumni/approved)
if ($method === 'GET' && preg_match('#/api/alumni/approved$#', $path)) {
    $all_alumni = db_get_all();
    $approved = [];
    foreach ($all_alumni as $a) {
        if (isset($a['status']) && $a['status'] === 'Approved') {
            $approved[] = $a;
        }
    }
    // Sort by created_at descending (newest first)
    usort($approved, function($a, $b) {
        $da = isset($a['created_at']) ? $a['created_at'] : '';
        $db = isset($b['created_at']) ? $b['created_at'] : '';
        return strcmp($db, $da);
    });
    json_response($approved);
}

// 4. Admin: Fetch All Alumni (Pending & Approved) (GET /api/admin/alumni)
if ($method === 'GET' && preg_match('#/api/admin/alumni$#', $path)) {
    verify_admin();
    $all_alumni = db_get_all();
    // Sort by created_at descending
    usort($all_alumni, function($a, $b) {
        $da = isset($a['created_at']) ? $a['created_at'] : '';
        $db = isset($b['created_at']) ? $b['created_at'] : '';
        return strcmp($db, $da);
    });
    json_response($all_alumni);
}

// 5. Admin: Verify Passcode (POST /api/admin/verify-passcode)
if ($method === 'POST' && preg_match('#/api/admin/verify-passcode$#', $path)) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $passcode = isset($input['passcode']) ? $input['passcode'] : null;
    if ($passcode === ADMIN_PASSCODE) {
        json_response(['success' => true, 'message' => 'Passcode verification successful!']);
    } else {
        json_response(['success' => false, 'message' => 'Invalid passcode.'], 401);
    }
}

// 6. Admin: Approve Alumni (POST /api/admin/alumni/([^/]+)/approve)
if ($method === 'POST' && preg_match('#/api/admin/alumni/([^/]+)/approve$#', $path, $matches)) {
    verify_admin();
    $record_id = $matches[1];

    $db_handle = fopen(DB_FILE, 'c+');
    if ($db_handle) {
        flock($db_handle, LOCK_EX);
        $size = filesize(DB_FILE);
        $records = [];
        if ($size > 0) {
            $content = fread($db_handle, $size);
            $records = json_decode($content, true) ?: [];
        }
        
        $updated = false;
        foreach ($records as &$r) {
            if (strval($r['id']) === strval($record_id)) {
                $r['status'] = 'Approved';
                $updated = true;
                break;
            }
        }

        if ($updated) {
            ftruncate($db_handle, 0);
            rewind($db_handle);
            fwrite($db_handle, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($db_handle);
            flock($db_handle, LOCK_UN);
            fclose($db_handle);
            json_response(['success' => true, 'message' => 'Alumni registration approved successfully!']);
        } else {
            flock($db_handle, LOCK_UN);
            fclose($db_handle);
            json_response(['success' => false, 'message' => 'Failed to approve alumni registration or alumnus not found.'], 404);
        }
    } else {
        json_response(['success' => false, 'message' => 'Database access error.'], 500);
    }
}

// 7. Admin: Delete/Reject Alumni (DELETE /api/admin/alumni/([^/]+))
if ($method === 'DELETE' && preg_match('#/api/admin/alumni/([^/]+)$#', $path, $matches)) {
    verify_admin();
    $record_id = $matches[1];

    $db_handle = fopen(DB_FILE, 'c+');
    if ($db_handle) {
        flock($db_handle, LOCK_EX);
        $size = filesize(DB_FILE);
        $records = [];
        if ($size > 0) {
            $content = fread($db_handle, $size);
            $records = json_decode($content, true) ?: [];
        }

        $image_to_delete = null;
        $new_records = [];
        $found = false;
        foreach ($records as $r) {
            if (strval($r['id']) === strval($record_id)) {
                $found = true;
                $url = isset($r['image_url']) ? $r['image_url'] : '';
                if (!empty($url)) {
                    $url_parts = explode('/', $url);
                    $image_to_delete = end($url_parts);
                }
            } else {
                $new_records[] = $r;
            }
        }

        if ($found) {
            ftruncate($db_handle, 0);
            rewind($db_handle);
            fwrite($db_handle, json_encode($new_records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($db_handle);
            flock($db_handle, LOCK_UN);
            fclose($db_handle);

            // Delete local image file
            if (!empty($image_to_delete)) {
                $image_path = UPLOAD_DIR . '/' . $image_to_delete;
                if (file_exists($image_path)) {
                    @unlink($image_path);
                }
            }
            json_response(['success' => true, 'message' => 'Alumni record deleted successfully!']);
        } else {
            flock($db_handle, LOCK_UN);
            fclose($db_handle);
            json_response(['success' => false, 'message' => 'Failed to delete alumni record or alumnus not found.'], 404);
        }
    } else {
        json_response(['success' => false, 'message' => 'Database access error.'], 500);
    }
}

// 8. Admin: Export Approved Alumni as Excel (GET /api/admin/alumni/export)
if ($method === 'GET' && preg_match('#/api/admin/alumni/export$#', $path)) {
    // Authenticate passcode from query parameters or header
    $passcode = isset($_GET['passcode']) ? $_GET['passcode'] : (isset($_SERVER['HTTP_X_ADMIN_PASSCODE']) ? $_SERVER['HTTP_X_ADMIN_PASSCODE'] : null);
    if (!$passcode || $passcode !== ADMIN_PASSCODE) {
        json_response(['success' => false, 'message' => 'Unauthorized admin passcode.'], 401);
    }

    $all_alumni = db_get_all();
    $approved = [];
    foreach ($all_alumni as $a) {
        if (isset($a['status']) && $a['status'] === 'Approved') {
            $approved[] = $a;
        }
    }

    // Sort year-wise ascending, and branch-wise (department) ascending
    usort($approved, function($a, $b) {
        $ya = trim(isset($a['graduation_year']) ? $a['graduation_year'] : '');
        $yb = trim(isset($b['graduation_year']) ? $b['graduation_year'] : '');
        if ($ya !== $yb) {
            return strcmp($ya, $yb);
        }
        $da = strtolower(trim(isset($a['department']) ? $a['department'] : ''));
        $db = strtolower(trim(isset($b['department']) ? $b['department'] : ''));
        return strcmp($da, $db);
    });

    $headers = ["Name", "Email", "Mobile Number", "Department", "Graduation Year", "Designation", "Company", "Registration Date"];
    $rows = [];
    foreach ($approved as $alumnus) {
        $rows[] = [
            isset($alumnus['name']) ? $alumnus['name'] : '',
            isset($alumnus['email']) ? $alumnus['email'] : '',
            isset($alumnus['mobile_number']) ? $alumnus['mobile_number'] : '',
            isset($alumnus['department']) ? $alumnus['department'] : '',
            isset($alumnus['graduation_year']) ? $alumnus['graduation_year'] : '',
            isset($alumnus['designation']) ? $alumnus['designation'] : '',
            isset($alumnus['company']) ? $alumnus['company'] : '',
            isset($alumnus['created_at']) ? $alumnus['created_at'] : ''
        ];
    }

    // Fallback: If ZipArchive class is missing, output a styled HTML table served as an Excel file (.xls)
    if (!class_exists('ZipArchive')) {
        $current_date = date("d-m-Y");
        $filename = "WIT Alumni report ($current_date).xls";

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head>';
        echo '<meta http-equiv="Content-type" content="text/html;charset=utf-8" />';
        echo '<style>';
        echo '  table { border-collapse: collapse; }';
        echo '  th { background-color: #1E293B; color: #FFFFFF; font-family: Arial, sans-serif; font-size: 11pt; font-weight: bold; text-align: center; vertical-align: middle; height: 30px; border: 0.5pt solid #CCCCCC; padding: 5px; }';
        echo '  td { font-family: Calibri, sans-serif; font-size: 11pt; border: 0.5pt solid #CCCCCC; padding: 5px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<table>';
        
        // Header
        echo '<tr>';
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';

        // Data Rows
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $val) {
                echo '<td>' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }

        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit;
    }

    // Calculate column widths
    $widths = [];
    foreach ($headers as $col_idx => $header) {
        $max_len = strlen($header);
        foreach ($rows as $row) {
            $val_len = strlen(strval($row[$col_idx]));
            if ($val_len > $max_len) {
                $max_len = $val_len;
            }
        }
        $widths[$col_idx] = max($max_len + 4, 12);
    }

    // Escape helper for XML
    $xml_escape = function($str) {
        return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    // Build worksheet XML
    $sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
    $sheet_xml .= '  <cols>' . "\n";
    foreach ($widths as $col_idx => $w) {
        $col_num = $col_idx + 1;
        $sheet_xml .= '    <col min="' . $col_num . '" max="' . $col_num . '" width="' . $w . '" customWidth="1"/>' . "\n";
    }
    $sheet_xml .= '  </cols>' . "\n";
    $sheet_xml .= '  <sheetData>' . "\n";
    
    // Header row with background styling
    $sheet_xml .= '    <row r="1" ht="25" customHeight="1">' . "\n";
    foreach ($headers as $col_idx => $header) {
        $cell_ref = chr(65 + $col_idx) . '1';
        $sheet_xml .= '      <c r="' . $cell_ref . '" s="1" t="inlineStr"><is><t>' . $xml_escape($header) . '</t></is></c>' . "\n";
    }
    $sheet_xml .= '    </row>' . "\n";

    // Data rows
    $row_num = 2;
    foreach ($rows as $row) {
        $sheet_xml .= '    <row r="' . $row_num . '">' . "\n";
        foreach ($row as $col_idx => $val) {
            $cell_ref = chr(65 + $col_idx) . $row_num;
            $sheet_xml .= '      <c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $xml_escape($val) . '</t></is></c>' . "\n";
        }
        $sheet_xml .= '    </row>' . "\n";
        $row_num++;
    }
    $sheet_xml .= '  </sheetData>' . "\n";
    $sheet_xml .= '</worksheet>';

    // Build Styles XML with font family Arial, size 11, bold, color white, fill #1E293B for headers
    $styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $styles_xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
    $styles_xml .= '  <fonts count="2">' . "\n";
    $styles_xml .= '    <font><sz val="11"/><name val="Calibri"/><family val="2"/></font>' . "\n";
    $styles_xml .= '    <font><sz val="11"/><name val="Arial"/><family val="2"/><b/><color rgb="FFFFFFFF"/></font>' . "\n";
    $styles_xml .= '  </fonts>' . "\n";
    $styles_xml .= '  <fills count="3">' . "\n";
    $styles_xml .= '    <fill><patternFill patternType="none"/></fill>' . "\n";
    $styles_xml .= '    <fill><patternFill patternType="gray125"/></fill>' . "\n";
    $styles_xml .= '    <fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/><bgColor indexed="64"/></patternFill></fill>' . "\n";
    $styles_xml .= '  </fills>' . "\n";
    $styles_xml .= '  <borders count="1">' . "\n";
    $styles_xml .= '    <border><left/><right/><top/><bottom/></border>' . "\n";
    $styles_xml .= '  </borders>' . "\n";
    $styles_xml .= '  <cellStyleXfs count="1">' . "\n";
    $styles_xml .= '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' . "\n";
    $styles_xml .= '  </cellStyleXfs>' . "\n";
    $styles_xml .= '  <cellXfs count="2">' . "\n";
    $styles_xml .= '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\n";
    $styles_xml .= '    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">' . "\n";
    $styles_xml .= '      <alignment horizontal="center" vertical="center"/>' . "\n";
    $styles_xml .= '    </xf>' . "\n";
    $styles_xml .= '  </cellXfs>' . "\n";
    $styles_xml .= '</styleSheet>';

    // Build Workbook XML
    $workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $workbook_xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
    $workbook_xml .= '  <sheets>' . "\n";
    $workbook_xml .= '    <sheet name="Approved Alumni" sheetId="1" r:id="rId1"/>' . "\n";
    $workbook_xml .= '  </sheets>' . "\n";
    $workbook_xml .= '</workbook>';

    // Build package relationships
    $rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $rels_xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
    $rels_xml .= '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n";
    $rels_xml .= '</Relationships>';

    // Build workbook relationships
    $workbook_rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $workbook_rels_xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n";
    $workbook_rels_xml .= '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n";
    $workbook_rels_xml .= '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n";
    $workbook_rels_xml .= '</Relationships>';

    // Build Content Types
    $content_types_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $content_types_xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n";
    $content_types_xml .= '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n";
    $content_types_xml .= '  <Default Extension="xml" ContentType="application/xml"/>' . "\n";
    $content_types_xml .= '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n";
    $content_types_xml .= '  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";
    $content_types_xml .= '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n";
    $content_types_xml .= '</Types>';

    // Create the ZIP/XLSX file
    $temp_file = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $zip->addFromString('[Content_Types].xml', $content_types_xml);
        $zip->addFromString('_rels/.rels', $rels_xml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels_xml);
        $zip->addFromString('xl/workbook.xml', $workbook_xml);
        $zip->addFromString('xl/styles.xml', $styles_xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
        $zip->close();

        $current_date = date("d-m-Y");
        $filename = "WIT Alumni report ($current_date).xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temp_file));
        header('Cache-Control: max-age=0');

        readfile($temp_file);
        @unlink($temp_file);
        exit;
    } else {
        json_response(['success' => false, 'message' => 'Failed to generate Excel file.'], 500);
    }
}

// 9. Route Not Found
json_response(['success' => false, 'message' => 'API route not found: ' . $path], 404);
