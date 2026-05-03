<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LegacyProxyController extends Controller
{
    public function dashboard(Request $request, ?string $path = null): Response
    {
        $baseDirectory = base_path('legacy/dashboard');
        $resolvedPath = $this->resolvePath($baseDirectory, $path ?? 'index.php');

        if (!$resolvedPath) {
            abort(404);
        }

        if ($guardResponse = $this->guardDashboardPathAccess($request, $baseDirectory, $resolvedPath)) {
            return $guardResponse;
        }

        if (strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION)) === 'php') {
            return $this->executePhpFile($request, $resolvedPath);
        }

        return response()->file($resolvedPath);
    }

    private function guardDashboardPathAccess(Request $request, string $baseDirectoryAbsolute, string $resolvedPath): ?Response
    {
        $user = $request->user();
        $role = trim((string) ($user?->role ?? 'admin'));
        if ($role !== 'kafedra_mudiri') {
            return null;
        }

        $basePath = realpath($baseDirectoryAbsolute);
        if (!$basePath) {
            return null;
        }

        $basePathNormalized = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
        $resolvedPathNormalized = str_replace('\\', '/', $resolvedPath);
        $relativePath = Str::startsWith($resolvedPathNormalized, $basePathNormalized)
            ? substr($resolvedPathNormalized, strlen($basePathNormalized))
            : ltrim((string) $request->route('path', 'index.php'), '/');
        $relativePath = $relativePath !== '' ? $relativePath : 'index.php';

        $allowedPaths = [
            'index.php',
            'oqtuvchilar.php',
            'oquv-rejalar.php',
            'ishchi-oquv-rejalar.php',
            'oquv-yuklamalar.php',
            'magistr-doktorant-yuklamalar.php',
            'oquv-taqsimotlar.php',
            'oqituvchi-taqsimotlar.php',
            'oqituvchi-bildirgi.php',
            'profil-sozlamalari.php',
            'get/oqituvchilar_table.php',
            'get/oquv_yuklama_table.php',
            'get/oquv_taqsimoti_table.php',
            'get/oqituvchi_taqsimoti_table.php',
            'insert/add_oqituvchi.php',
            'insert/update_oqituvchi.php',
            'insert/delete_oqituvchi.php',
            'insert/add_oquv_taqsimot.php',
            'insert/update_mening_hisobim.php',
            'api/get_oquv_reja_by_yuklama.php',
            'api/get_teacher_total_hours.php',
        ];

        if (in_array($relativePath, $allowedPaths, true)) {
            return null;
        }

        if ($request->expectsJson() || preg_match('#^(api|get|insert)/#', $relativePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Ushbu bo‘limga ruxsat yo‘q.',
            ], 403);
        }

        abort(403, 'Ushbu bo‘limga ruxsat yo‘q.');
    }

    public function asset(string $path): Response
    {
        $resolvedPath = $this->resolvePath(public_path('assets'), $path);

        if (!$resolvedPath) {
            abort(404);
        }

        $extension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            default => (mime_content_type($resolvedPath) ?: 'application/octet-stream'),
        };

        return response()->file($resolvedPath, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function resolvePath(string $baseDirectoryAbsolute, string $relativePath): ?string
    {
        $normalizedPath = trim(str_replace('\\', '/', $relativePath), '/');

        if ($normalizedPath === '' || Str::contains($normalizedPath, '..')) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9_\\-\\.\\/]+$/', $normalizedPath)) {
            return null;
        }

        $basePath = realpath($baseDirectoryAbsolute);

        if (!$basePath) {
            return null;
        }

        $candidate = realpath($basePath.$this->directorySeparator().str_replace('/', $this->directorySeparator(), $normalizedPath));

        if (!$candidate || !is_file($candidate)) {
            return null;
        }

        $basePathNormalized = rtrim(str_replace('\\', '/', $basePath), '/').'/';
        $candidateNormalized = str_replace('\\', '/', $candidate);

        if (!Str::startsWith($candidateNormalized, $basePathNormalized)) {
            return null;
        }

        return $candidate;
    }

    private function executePhpFile(Request $request, string $filePath): Response
    {
        $headersBefore = headers_list();
        $workingDirectoryBefore = getcwd();

        $backupGet = $_GET;
        $backupPost = $_POST;
        $backupRequest = $_REQUEST;
        $backupFiles = $_FILES;
        $backupCookie = $_COOKIE;
        $backupServer = $_SERVER;
        $backupSession = $_SESSION ?? [];

        try {
            $_GET = $request->query->all();
            $_POST = $request->request->all();
            $_REQUEST = array_merge($_GET, $_POST);
            $_FILES = $request->files->all();
            $_COOKIE = $request->cookies->all();

            $_SERVER = array_merge($_SERVER, [
                'REQUEST_METHOD' => $request->method(),
                'REQUEST_URI' => $request->getRequestUri(),
                'QUERY_STRING' => (string) $request->server('QUERY_STRING', ''),
                'SCRIPT_FILENAME' => $filePath,
                'SCRIPT_NAME' => '/'.$request->path(),
                'PHP_SELF' => '/'.$request->path(),
            ]);

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = $request->session()->all();
            }

            if ($workingDirectoryBefore !== false) {
                chdir(dirname($filePath));
            }

            ob_start();
            try {
                include $filePath;
                $content = (string) ob_get_clean();
            } catch (\Throwable $exception) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                throw $exception;
            }

            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION) && is_array($_SESSION)) {
                foreach ($_SESSION as $key => $value) {
                    $request->session()->put($key, $value);
                }
            }
        } finally {
            if ($workingDirectoryBefore !== false) {
                chdir($workingDirectoryBefore);
            }

            $_GET = $backupGet;
            $_POST = $backupPost;
            $_REQUEST = $backupRequest;
            $_FILES = $backupFiles;
            $_COOKIE = $backupCookie;
            $_SERVER = $backupServer;
            $_SESSION = $backupSession;
        }

        $response = response($content);
        $headersAfter = headers_list();
        $newHeaders = array_values(array_diff($headersAfter, $headersBefore));

        foreach ($newHeaders as $headerLine) {
            if (!str_contains($headerLine, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $headerLine, 2);
            $response->headers->set(trim($name), trim($value), true);
        }

        if (!$response->headers->has('Content-Type')) {
            $trimmed = ltrim($content);
            $looksLikeJson = Str::startsWith($trimmed, '{') || Str::startsWith($trimmed, '[');

            $response->headers->set(
                'Content-Type',
                $looksLikeJson ? 'application/json; charset=UTF-8' : 'text/html; charset=UTF-8'
            );
        }

        return $response;
    }

    private function directorySeparator(): string
    {
        return DIRECTORY_SEPARATOR;
    }
}
