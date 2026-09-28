<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Failed Lead Import Records</title>
</head>
<body>
    <h2>Lead CSV Import Completed</h2>

    <p>
        Your lead CSV import has been completed.
    </p>

    <p>
        <strong>File:</strong>
        {{ $import->original_filename }}
    </p>

    <p>
        <strong>Total Records:</strong>
        {{ $import->total_records }}
    </p>

    <p>
        <strong>Successful:</strong>
        {{ $import->success_count }}
    </p>

    <p>
        <strong>Failed:</strong>
        {{ $import->failed_count }}
    </p>

    <p>
        The failed records CSV is attached to this email.
    </p>
</body>
</html>
