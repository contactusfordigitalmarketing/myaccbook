<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "myaccbook";
$port = 3308;
$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$acknowledgmentMessage = "";
$flashMessage = "";
$openingBalanceSubmittedToday = false;
$closeShopSubmittedToday = false;

// Check if Opening Balance has been submitted today
$sql = "SELECT * FROM entries WHERE type = 'Opening Balance' AND DATE(date_time) = CURDATE()";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $openingBalanceSubmittedToday = true;
}

// Check if Close Shop has been submitted today
$sql = "SELECT * FROM entries WHERE type = 'Close Shop' AND DATE(date_time) = CURDATE()";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $closeShopSubmittedToday = true;
}

$fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-d');
$toDate = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
$searchKeyword = isset($_POST['search_keyword']) ? $_POST['search_keyword'] : '';
$grandTotal = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submitEntry'])) {
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        $remarks = $_POST['remarks'] ?? '';
        
        if ($type == 'Debit') {
            $amount = -abs($amount);
        }

        // Prevent multiple entries for Opening Balance or Close Shop
        if (($type == 'Opening Balance' && $openingBalanceSubmittedToday) || ($type == 'Close Shop' && $closeShopSubmittedToday)) {
            $flashMessage = "You can only submit one '$type' entry per day!";
        } else {
            $sql = "INSERT INTO entries (type, amount, remarks) VALUES ('$type', '$amount', '$remarks')";
            if ($conn->query($sql) === TRUE) {
                $acknowledgmentMessage = "$type entry added successfully!";
                $flashMessage = "$type entry added successfully!";
                // Update the flag for opening balance or close shop
                if ($type == 'Opening Balance') {
                    $openingBalanceSubmittedToday = true;
                } elseif ($type == 'Close Shop') {
                    $closeShopSubmittedToday = true;
                }
            } else {
                $acknowledgmentMessage = "Error: " . $conn->error;
            }
        }
    }

    // Handle Close Shop submission
    if (isset($_POST['closeShop'])) {
        // Insert the grand total as a Close Shop entry
        $grandTotal = $_POST['grand_total'];
        
        // Prevent multiple Close Shop entries for the same day
        if ($closeShopSubmittedToday) {
            $flashMessage = "You can only submit one 'Close Shop' entry per day!";
        } else {
            $sql = "INSERT INTO entries (type, amount, remarks) VALUES ('Close Shop', '$grandTotal', 'End of day closing balance')";
            if ($conn->query($sql) === TRUE) {
                $flashMessage = "Shop closed successfully! Grand Total of $grandTotal recorded.";
                $closeShopSubmittedToday = true;  // Set flag to prevent further submissions
            } else {
                $flashMessage = "Error: " . $conn->error;
            }
        }
    }
}

// Fetch entries for the selected date range and search keyword
$sql = "SELECT * FROM entries WHERE DATE(date_time) BETWEEN '$fromDate' AND '$toDate' AND (remarks LIKE '%$searchKeyword%' OR type LIKE '%$searchKeyword%') ORDER BY date_time ASC";
$result = $conn->query($sql);
$entries = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
}

$totalCredit = 0;
$totalDebit = 0;
$openingBalance = 0;

foreach ($entries as $entry) {
    if ($entry['type'] == 'Credit') {
        $totalCredit += $entry['amount'];
    } elseif ($entry['type'] == 'Debit') {
        $totalDebit += $entry['amount'];
    } elseif ($entry['type'] == 'Opening Balance') {
        $openingBalance = $entry['amount'];
    }
}

$grandTotal = $openingBalance + $totalCredit + $totalDebit;
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Entry System - MyAccBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="http://localhost/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        .heading-container {
            background-color: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .main-container {
            display: flex;
            flex-grow: 1;
            padding: 0 20px;
            overflow: hidden;
        }

        .make-entry-container, .entry-summary-container {
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
        }

        .make-entry-container {
            width: 30%;
            position: relative;
        }

        .entry-summary-container {
            width: 70%;
            height: 100%;
            overflow-y: auto;
        }

        .form-section input {
            width: 100%;
        }

        .table-container {
            max-height: 300px;
            overflow-y: auto;
        }

        .table th, .table td {
            text-align: center;
        }

        .table th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 1;
        }

        footer {
            background-color: #343a40;
            color: white;
            padding: 10px;
            position: fixed;
            bottom: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container-fluid {
            padding: 0;
        }

        .credit {
            background-color: #28a745;
            color: white;
        }

        .debit {
            background-color: #dc3545;
            color: white;
        }

        .opening-balance {
            background-color: #17a2b8;
            color: white;
        }

        #dateTime {
            font-size: 0.9em;
        }

        .entry-form-container {
            display: none;
        }

        .totals {
            padding: 20px 0;
        }

        .total-item {
            font-size: 1.2em;
            font-weight: bold;
        }

        .total-item .label {
            font-size: 1em;
            color: #6c757d;
        }

        .total-item .amount {
            color: #28a745;
        }

        .grand-total {
            background-color: white;
            color: black;
            padding: 10px;
            font-size: 1.3em;
            font-weight: bold;
            border: 2px solid #007bff;
        }

        .acknowledgment-message {
            position: fixed;
            bottom: 10px;
            left: 20px;
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            z-index: 100;
            width: auto;
            display: none;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }

        /* Flash Message Style */
        .flash-message {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            display: none;
            z-index: 1000;
            font-size: 16px;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }

        .flash-message.show {
            display: block;
            opacity: 1;
        }

        /* Hide all elements except table when printing */
        @media print {
            body * {
                visibility: hidden;
            }
            .printable-table, .printable-table * {
                visibility: visible;
            }
            .table-container {
                display: block;
            }
            footer, .heading-container, .make-entry-container {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid heading-container">
    <h1>MyAccBook</h1>
</div>

<div class="container-fluid main-container">
    <div class="make-entry-container">
        <button id="creditBtn" class="btn btn-success mb-3 w-100" onclick="showEntryForm('Credit')">Enter Credit Amount</button>
        <button id="debitBtn" class="btn btn-danger mb-3 w-100" onclick="showEntryForm('Debit')">Enter Debit Amount</button>
        <button id="openingBalanceBtn" class="btn btn-info mb-3 w-100" onclick="showEntryForm('Opening Balance')">Enter Opening Balance</button>
        <button id="closeShopBtn" class="btn btn-warning mb-3 w-100" onclick="closeShop()">Close Shop</button>
        <button id="downloadCSVBtn" class="btn btn-primary mb-3 w-100" onclick="downloadCSV()">Download CSV</button>
        <button class="btn btn-secondary mb-3 w-100" onclick="printPage()">Print Entries</button>

        <div id="entryFormContainer" class="entry-form-container">
            <form method="POST">
                <div class="form-section">
                    <input type="number" class="form-control mb-3" name="amount" placeholder="Amount" required>
                    <textarea class="form-control mb-3" name="remarks" placeholder="Remarks"></textarea>
                    <input type="hidden" name="type" id="entryType">
                    <button type="submit" name="submitEntry" class="btn btn-primary w-100">Submit</button>
                </div>
            </form>
        </div>

        <!-- Flash Message -->
        <div id="flashMessage" class="flash-message"><?= $flashMessage ?></div>
    </div>

    <div class="entry-summary-container">
        <h4>Entry Summary</h4>

        <form method="POST" class="mb-3">
            <div class="row">
                <div class="col-md-3">
                    <input type="date" name="from_date" value="<?= $fromDate ?>" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <input type="date" name="to_date" value="<?= $toDate ?>" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search_keyword" class="form-control" value="<?= $searchKeyword ?>" placeholder="Search...">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Fetch Data</button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="table table-bordered printable-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Remarks</th>
                        <th>Date and Time</th>
                    </tr>
                </thead>
                <tbody id="entriesTable">
                    <?php foreach ($entries as $entry): ?>
                        <tr class="<?= strtolower(str_replace(' ', '-', $entry['type'])) ?>">
                            <td><?= $entry['type'] ?></td>
                            <td><?= $entry['amount'] ?></td>
                            <td><?= $entry['remarks'] ?></td>
                            <td><?= $entry['date_time'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="totals">
            <div class="row">
                <div class="col-md-3 total-item">
                    <span class="label">Total Credit:</span>
                    <div class="amount"><?= $totalCredit ?></div>
                </div>
                <div class="col-md-3 total-item">
                    <span class="label">Total Debit:</span>
                    <div class="amount"><?= $totalDebit ?></div>
                </div>
                <div class="col-md-3 total-item">
                    <span class="label">Opening Balance:</span>
                    <div class="amount"><?= $openingBalance ?></div>
                </div>
                <div class="col-md-3 total-item grand-total">
                    <span class="label">Grand Total:</span>
                    <div class="amount"><?= $grandTotal ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acknowledgment Message -->
<?php if ($acknowledgmentMessage): ?>
    <div id="acknowledgmentMessage" class="acknowledgment-message"><?= $acknowledgmentMessage ?></div>
<?php endif; ?>

<footer>
    <div>© 2025 MyAccBook. All rights reserved.</div>
    <div id="dateTime"></div>
</footer>

<script>
    function updateDateTime() {
        const dateTimeElement = document.getElementById('dateTime');
        const now = new Date();
        const formattedDate = now.toLocaleDateString();
        const formattedTime = now.toLocaleTimeString();
        dateTimeElement.textContent = `${formattedDate} ${formattedTime}`;
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();

    // Show entry form for Credit, Debit, and Opening Balance
    function showEntryForm(type) {
        document.getElementById('entryType').value = type;
        document.getElementById('entryFormContainer').style.display = 'block';
    }

    // Automatically hide acknowledgment and flash message after 2 seconds
    window.onload = function() {
        const acknowledgmentMessage = document.getElementById('acknowledgmentMessage');
        const flashMessage = document.getElementById('flashMessage');

        if (acknowledgmentMessage) {
            acknowledgmentMessage.style.opacity = 1;
            setTimeout(() => acknowledgmentMessage.style.opacity = 0, 2000);
        }

        if (flashMessage) {
            flashMessage.classList.add('show');
            setTimeout(() => flashMessage.classList.remove('show'), 5000);
        }
    }

    // CSV download function
    function downloadCSV() {
        // Get the table data
        const table = document.querySelector('.table');
        const rows = table.querySelectorAll('tr');
        
        let csvContent = '';
        
        // Iterate through rows and extract the content
        rows.forEach((row, index) => {
            const columns = row.querySelectorAll('th, td');
            const rowData = [];
            
            columns.forEach(column => {
                rowData.push(column.innerText.trim());
            });
            
            // Join columns with comma and add to csv content
            csvContent += rowData.join(',') + '\n';
        });
        
        // Create a Blob from CSV string and download it
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'transactions.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Handle Close Shop submission
    function closeShop() {
        if (confirm("Are you sure you want to close the shop? This action is irreversible!")) {
            // Send form data to close shop
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="closeShop" value="true"><input type="hidden" name="grand_total" value="' + <?= $grandTotal ?> + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Print the entries table
    function printPage() {
        const printContents = document.querySelector('.printable-table').outerHTML;
        const originalContents = document.body.innerHTML;
        
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
    }
</script>
</body>
</html>
