import { useEffect, useRef, useState } from 'react'
import axios from 'axios'

const API_BASE_URL = 'http://127.0.0.1:8000/api'

function App() {
  const [file, setFile] = useState(null)
  const [importData, setImportData] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')

  const fileInputRef = useRef(null)

  /*
   * Poll import status while processing. this is updated function
   */
  useEffect(() => {
    if (!importData?.id) {
      return
    }

    if (
      importData.status === 'completed' ||
      importData.status === 'failed'
    ) {
      return
    }

    const interval = setInterval(async () => {
      try {
        const response = await axios.get(
          `${API_BASE_URL}/lead-imports/${importData.id}`
        )

        setImportData(response.data)
      } catch (err) {
        console.error(err)
      }
    }, 1000)

    return () => clearInterval(interval)
  }, [importData?.id, importData?.status])

  const handleFileChange = (event) => {
    const selectedFile = event.target.files?.[0]

    setError('')
    setImportData(null)

    if (!selectedFile) {
      setFile(null)
      return
    }

    const isCsv =
      selectedFile.name.toLowerCase().endsWith('.csv')

    if (!isCsv) {
      setFile(null)
      setError('Please select a CSV file.')
      return
    }

    setFile(selectedFile)
  }

  const handleUpload = async () => {
    if (!file) {
      setError('Please select a CSV file first.')
      return
    }

    setUploading(true)
    setError('')
    setImportData(null)

    try {
      const formData = new FormData()

      formData.append('file', file)

      const response = await axios.post(
        `${API_BASE_URL}/lead-imports`,
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      )

      /*
       * Upload API returns the created import.
       */
      setImportData({
        id: response.data.import.id,
        filename: response.data.import.original_filename,
        status: response.data.import.status,
        total_records: 0,
        processed_records: 0,
        success_count: 0,
        failed_count: 0,
      })

      /*
       * Clear selected file after successful upload.
       */
      setFile(null)

      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
    } catch (err) {
      console.error(err)

      if (err.response?.data?.message) {
        setError(err.response.data.message)
      } else {
        setError(
          'Unable to upload CSV. Please make sure Laravel is running.'
        )
      }
    } finally {
      setUploading(false)
    }
  }

  const totalRecords =
    importData?.total_records ?? 0

  const processedRecords =
    importData?.processed_records ?? 0

  const successCount =
    importData?.success_count ?? 0

  const failedCount =
    importData?.failed_count ?? 0

  const progress =
    totalRecords > 0
      ? Math.min(
          100,
          Math.round(
            (processedRecords / totalRecords) * 100
          )
        )
      : 0

  const isProcessing =
    importData?.status === 'pending' ||
    importData?.status === 'processing'

  return (
    <div className="min-h-screen bg-slate-100">
      <div className="mx-auto max-w-5xl px-4 py-10">

        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-slate-900">
            Lead CSV Import
          </h1>

          <p className="mt-2 text-slate-600">
            Upload a CSV file and import leads in the background.
          </p>
        </div>

        {/* Upload Card */}
        <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
          <h2 className="text-lg font-semibold text-slate-900">
            Upload CSV
          </h2>

          <p className="mt-1 text-sm text-slate-500">
            Supported format: CSV
          </p>

          <div className="mt-6">
            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,text/csv"
              onChange={handleFileChange}
              className="block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-4 file:border-0 file:bg-blue-600 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-white hover:file:bg-blue-700"
            />
          </div>

          {file && (
            <div className="mt-4 rounded-lg bg-blue-50 p-4">
              <p className="text-sm font-medium text-blue-900">
                Selected file
              </p>

              <p className="mt-1 text-sm text-blue-700">
                {file.name}
              </p>

              <p className="mt-1 text-xs text-blue-600">
                {(file.size / 1024 / 1024).toFixed(2)} MB
              </p>
            </div>
          )}

          {error && (
            <div className="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
              {error}
            </div>
          )}

          <button
            type="button"
            onClick={handleUpload}
            disabled={!file || uploading}
            className="mt-6 rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
          >
            {uploading ? 'Uploading...' : 'Upload CSV'}
          </button>
        </div>

        {/* Import Status */}
        {importData && (
          <div className="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">

            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
              <div>
                <p className="text-sm text-slate-500">
                  Import #{importData.id}
                </p>

                <h2 className="mt-1 text-lg font-semibold text-slate-900">
                  {importData.filename}
                </h2>
              </div>

              <StatusBadge status={importData.status} />
            </div>

            {/* Progress */}
            <div className="mt-8">
              <div className="mb-2 flex justify-between text-sm">
                <span className="font-medium text-slate-700">
                  Processing progress
                </span>

                <span className="font-semibold text-blue-600">
                  {progress}%
                </span>
              </div>

              <div className="h-3 overflow-hidden rounded-full bg-slate-200">
                <div
                  className="h-full rounded-full bg-blue-600 transition-all duration-500"
                  style={{
                    width: `${progress}%`,
                  }}
                />
              </div>

              <p className="mt-2 text-sm text-slate-500">
                {processedRecords.toLocaleString()} /{' '}
                {totalRecords.toLocaleString()} records processed
              </p>
            </div>

            {/* Statistics */}
            <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">

              <StatCard
                label="Total Records"
                value={totalRecords}
                color="slate"
              />

              <StatCard
                label="Successful"
                value={successCount}
                color="green"
              />

              <StatCard
                label="Failed"
                value={failedCount}
                color="red"
              />

            </div>

            {/* Processing message */}
            {isProcessing && (
              <div className="mt-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-700">
                Your CSV is being processed in the background. You can
                keep this page open to monitor the progress.
              </div>
            )}

            {/* Completed message */}
            {importData.status === 'completed' && (
              <div className="mt-6 rounded-lg bg-green-50 p-4">
                <p className="text-sm text-green-700">
                  Import completed successfully.
                </p>

                {failedCount > 0 && (
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        const response = await axios.get(
                          `${API_BASE_URL}/lead-imports/${importData.id}/failed-records`,
                          {
                            responseType: 'blob',
                          }
                        )

                        const url = window.URL.createObjectURL(
                          new Blob([response.data], {
                            type: 'text/csv',
                          })
                        )

                        const link = document.createElement('a')

                        link.href = url
                        link.setAttribute(
                          'download',
                          `failed_records_${importData.id}.csv`
                        )

                        document.body.appendChild(link)

                        link.click()

                        link.remove()

                        window.URL.revokeObjectURL(url)
                      } catch (error) {
                        console.error(
                          'Failed CSV download error:',
                          error
                        )
                      }
                    }}
                    className="mt-4 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                  >
                    Download Failed Records
                  </button>
                )}
              </div>
            )}


            {/* Failed message */}
            {importData.status === 'failed' && (
              <div className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                Import processing failed. Please check the import logs.
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

/*
 * Status badge
 */
function StatusBadge({ status }) {
  const styles = {
    pending:
      'bg-yellow-100 text-yellow-800',

    processing:
      'bg-blue-100 text-blue-800',

    completed:
      'bg-green-100 text-green-800',

    failed:
      'bg-red-100 text-red-800',
  }

  return (
    <span
      className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase ${
        styles[status] ||
        'bg-slate-100 text-slate-700'
      }`}
    >
      {status}
    </span>
  )
}

/*
 * Statistics card
 */
function StatCard({ label, value, color }) {
  const colors = {
    slate: 'text-slate-900',
    green: 'text-green-600',
    red: 'text-red-600',
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50 p-5">
      <p className="text-sm text-slate-500">
        {label}
      </p>

      <p
        className={`mt-2 text-2xl font-bold ${
          colors[color] || colors.slate
        }`}
      >
        {value.toLocaleString()}
      </p>
    </div>
  )
}

export default App
