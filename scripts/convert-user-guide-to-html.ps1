$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$sourcePath = Join-Path $root 'docs\foydalanuvchi-yoriqnomasi-full.md'
$targetPath = Join-Path $root 'docs\foydalanuvchi-yoriqnomasi.html'

if (-not (Test-Path $sourcePath)) {
    throw "Source markdown file not found: $sourcePath"
}

function Escape-Html {
    param([string]$Value)

    if ($null -eq $Value) {
        return ''
    }

    return [System.Net.WebUtility]::HtmlEncode($Value)
}

function Slugify {
    param([string]$Value)

    $slug = $Value.ToLowerInvariant()
    $slug = $slug -replace "[^a-z0-9\s-]", ''
    $slug = $slug -replace "\s+", '-'
    $slug = $slug.Trim('-')

    if ([string]::IsNullOrWhiteSpace($slug)) {
        return 'section'
    }

    return $slug
}

function Format-Inline {
    param([string]$Text)

    $escaped = Escape-Html $Text
    $escaped = [regex]::Replace($escaped, '\*\*(.+?)\*\*', '<strong>$1</strong>')
    $escaped = [regex]::Replace($escaped, '`([^`]+)`', '<code>$1</code>')
    return $escaped
}

$lines = Get-Content $sourcePath -Encoding UTF8
$htmlParts = New-Object System.Collections.Generic.List[string]
$toc = New-Object System.Collections.Generic.List[object]

$inUl = $false
$inOl = $false
$paragraph = New-Object System.Collections.Generic.List[string]

function Flush-Paragraph {
    param(
        [System.Collections.Generic.List[string]]$Paragraph,
        [System.Collections.Generic.List[string]]$HtmlParts
    )

    if ($Paragraph.Count -eq 0) {
        return
    }

    $text = ($Paragraph -join ' ').Trim()
    if ($text -ne '') {
        $HtmlParts.Add('<p>' + (Format-Inline $text) + '</p>')
    }
    $Paragraph.Clear()
}

function Close-Lists {
    param(
        [ref]$InUl,
        [ref]$InOl,
        [System.Collections.Generic.List[string]]$HtmlParts
    )

    if ($InUl.Value) {
        $HtmlParts.Add('</ul>')
        $InUl.Value = $false
    }

    if ($InOl.Value) {
        $HtmlParts.Add('</ol>')
        $InOl.Value = $false
    }
}

foreach ($line in $lines) {
    $trimmed = $line.Trim()

    if ($trimmed -eq '') {
        Flush-Paragraph -Paragraph $paragraph -HtmlParts $htmlParts
        Close-Lists -InUl ([ref]$inUl) -InOl ([ref]$inOl) -HtmlParts $htmlParts
        continue
    }

    if ($trimmed -eq '---') {
        Flush-Paragraph -Paragraph $paragraph -HtmlParts $htmlParts
        Close-Lists -InUl ([ref]$inUl) -InOl ([ref]$inOl) -HtmlParts $htmlParts
        $htmlParts.Add('<hr>')
        continue
    }

    if ($trimmed -match '^(#{1,3})\s+(.+)$') {
        Flush-Paragraph -Paragraph $paragraph -HtmlParts $htmlParts
        Close-Lists -InUl ([ref]$inUl) -InOl ([ref]$inOl) -HtmlParts $htmlParts

        $level = $matches[1].Length
        $title = $matches[2].Trim()
        $id = Slugify $title

        $toc.Add([pscustomobject]@{
            Level = $level
            Title = $title
            Id = $id
        })

        $htmlParts.Add("<h$level id=""$id"">" + (Format-Inline $title) + "</h$level>")
        continue
    }

    if ($trimmed -match '^\-\s+(.+)$') {
        Flush-Paragraph -Paragraph $paragraph -HtmlParts $htmlParts
        if ($inOl) {
            $htmlParts.Add('</ol>')
            $inOl = $false
        }
        if (-not $inUl) {
            $htmlParts.Add('<ul>')
            $inUl = $true
        }
        $htmlParts.Add('<li>' + (Format-Inline $matches[1].Trim()) + '</li>')
        continue
    }

    if ($trimmed -match '^\d+\.\s+(.+)$') {
        Flush-Paragraph -Paragraph $paragraph -HtmlParts $htmlParts
        if ($inUl) {
            $htmlParts.Add('</ul>')
            $inUl = $false
        }
        if (-not $inOl) {
            $htmlParts.Add('<ol>')
            $inOl = $true
        }
        $htmlParts.Add('<li>' + (Format-Inline $matches[1].Trim()) + '</li>')
        continue
    }

    $paragraph.Add($trimmed)
}

Flush-Paragraph -Paragraph $paragraph -HtmlParts $htmlParts
Close-Lists -InUl ([ref]$inUl) -InOl ([ref]$inOl) -HtmlParts $htmlParts

$tocHtml = New-Object System.Collections.Generic.List[string]
$tocHtml.Add('<ul class="toc-list">')
foreach ($entry in $toc) {
    if ($entry.Level -gt 3) {
        continue
    }

    $class = "toc-level-$($entry.Level)"
    $tocHtml.Add("<li class=""$class""><a href=""#$($entry.Id)"">$(Escape-Html $entry.Title)</a></li>")
}
$tocHtml.Add('</ul>')

$bodyHtml = ($htmlParts -join "`r`n")
$tocMarkup = ($tocHtml -join "`r`n")

$html = @"
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O'quv Jarayoni Boshqaruvi - Foydalanuvchi Yo'riqnomasi</title>
    <style>
        :root {
            --bg: #f3f6f4;
            --paper: #ffffff;
            --ink: #162126;
            --muted: #59656c;
            --line: #dce5df;
            --accent: #0a9b52;
            --accent-soft: #e8f7ef;
            --danger-soft: #fff1ef;
            --toc: #15382b;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, #edf4ef 0%, #f9fbfa 100%);
            line-height: 1.65;
        }

        .shell {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            min-height: 100vh;
        }

        .toc {
            position: sticky;
            top: 0;
            align-self: start;
            height: 100vh;
            overflow-y: auto;
            background: var(--toc);
            color: #f4fbf7;
            padding: 28px 22px 38px;
        }

        .toc h1 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 1.15;
        }

        .toc p {
            margin: 0 0 18px;
            color: #d4e6db;
            font-size: 14px;
        }

        .toc-card {
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .toc-card strong {
            display: block;
            color: white;
            margin-bottom: 4px;
        }

        .toc-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .toc-list li {
            margin: 8px 0;
        }

        .toc-level-1 {
            margin-top: 14px;
            font-weight: 700;
        }

        .toc-level-2 {
            padding-left: 12px;
        }

        .toc-level-3 {
            padding-left: 24px;
            font-size: 14px;
        }

        .toc a {
            color: #f4fbf7;
            text-decoration: none;
        }

        .toc a:hover {
            text-decoration: underline;
        }

        .page {
            padding: 34px;
        }

        .paper {
            max-width: 1120px;
            margin: 0 auto;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: 0 26px 50px rgba(17, 45, 31, 0.08);
            overflow: hidden;
        }

        .cover {
            padding: 42px 46px 30px;
            background: radial-gradient(circle at top left, #effbf3 0%, #ffffff 60%);
            border-bottom: 1px solid var(--line);
        }

        .cover .eyebrow {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .cover h1 {
            margin: 0 0 10px;
            font-size: 40px;
            line-height: 1.08;
        }

        .cover p {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            max-width: 880px;
        }

        .body {
            padding: 32px 46px 54px;
        }

        h1, h2, h3 {
            color: #102027;
        }

        h1 {
            font-size: 34px;
            margin-top: 0;
        }

        h2 {
            margin-top: 38px;
            font-size: 28px;
            border-bottom: 2px solid #e5efe8;
            padding-bottom: 10px;
        }

        h3 {
            margin-top: 26px;
            font-size: 21px;
        }

        p {
            margin: 12px 0;
        }

        ul, ol {
            margin: 12px 0 14px;
            padding-left: 24px;
        }

        li {
            margin: 6px 0;
        }

        hr {
            border: 0;
            border-top: 1px dashed #cdd9d1;
            margin: 28px 0;
        }

        strong {
            color: #0e1f24;
        }

        code {
            background: #f1f6f3;
            border: 1px solid #dbe7df;
            border-radius: 6px;
            padding: 2px 6px;
            font-family: Consolas, monospace;
            font-size: 0.95em;
        }

        blockquote {
            margin: 16px 0;
            padding: 14px 18px;
            border-left: 5px solid var(--accent);
            background: var(--accent-soft);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }

        th, td {
            border: 1px solid var(--line);
            padding: 12px 14px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #eff8f2;
        }

        @media (max-width: 1200px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .toc {
                position: static;
                height: auto;
            }
        }

        @media (max-width: 760px) {
            .page {
                padding: 16px;
            }

            .cover,
            .body {
                padding-left: 20px;
                padding-right: 20px;
            }

            .cover h1 {
                font-size: 30px;
            }
        }

        @media print {
            body {
                background: white;
            }

            .shell {
                display: block;
            }

            .toc {
                display: none;
            }

            .page {
                padding: 0;
            }

            .paper {
                border: none;
                box-shadow: none;
            }

            h2 {
                page-break-before: always;
            }

            h2:first-of-type {
                page-break-before: auto;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="toc">
            <h1>Foydalanuvchi yo'riqnomasi</h1>
            <p>Word va PDF ga chiqarishga tayyor HTML hujjat</p>

            <div class="toc-card">
                <strong>Tizim</strong>
                O'quv Jarayoni Boshqaruvi
            </div>

            <div class="toc-card">
                <strong>Qamrov</strong>
                Login, sozlamalar, o'quv reja, yuklama, taqsimot va acceptance checklist
            </div>

            $tocMarkup
        </aside>

        <main class="page">
            <article class="paper">
                <header class="cover">
                    <div class="eyebrow">To'liq HTML hujjat</div>
                    <h1>O'quv Jarayoni Boshqaruvi</h1>
                    <p>Ushbu hujjat tizimning bo'limlari bo'yicha foydalanuvchi yo'riqnomasi hisoblanadi. Hujjat to'g'ridan-to'g'ri brauzerda o'qish, Word ga ko'chirish yoki print orqali PDF olish uchun tayyorlangan.</p>
                </header>

                <div class="body">
                    $bodyHtml
                </div>
            </article>
        </main>
    </div>
</body>
</html>
"@

Set-Content -Path $targetPath -Value $html -Encoding UTF8
Write-Host "Generated HTML user guide: $targetPath"
