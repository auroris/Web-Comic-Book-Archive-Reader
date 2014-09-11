<?PHP
// Development settings -- comment out the next three lines in production
header('Access-Control-Allow-Origin: *'); // CORS All-Access
error_reporting(E_ALL);
ini_set('display_errors', 'on');

/////////////////////////////////////////////////////////////////////////////////
// Entry point for getting JSON
// action=books
// action=toc&comic=file.cbz
// action=image&comic=file.cbz&page=image.jpg

// Returns a list of comic books as json format
if ($_GET["action"] == "books")
{
    header('Content-type: application/json');
    print json_encode(getFileList(dirname(__FILE__)));
}

// Returns the table of contents as a JSON string
if ($_GET["action"] == "toc")
{
    header('Content-type: application/json');
    print json_encode(getToC(basename(realpath($_GET["comic"]))));
}

// Get an image out of an archive file by name
if ($_GET["action"] == "image")
{
    passImage(basename(realpath($_GET["comic"])), $_GET["page"]);
}

/////////////////////////////////////////////////////////////////////////////////
// Return an array of files in $path that are archive files
function getFileList($path)
{
    $files = scandir($path);
    $jsonfiles = array();
    for ($i = 0; $i < count($files); $i++)
    {
        if (endsWith($files[$i], ".cbz") || endsWith($files[$i], ".cbr"))
        {
            $jsonfiles[] = $files[$i];
        }
    }
    return $jsonfiles;
}

/////////////////////////////////////////////////////////////////////////////////
// Get the table of contents from an archive file; returns a complete list of image files contained within
function getToC($archive)
{
    $toc = array();

    if (endsWith($archive, ".cbz"))
    {
        $zip = new ZipArchive();
        if ($zip->open($archive))
        {
            for ($i = 0; $i < $zip->numFiles; $i++)
            {
                if (endsWith($zip->getNameIndex($i), '.jpg') 
                    || endsWith($zip->getNameIndex($i), '.gif')
                    || endsWith($zip->getNameIndex($i), '.png'))
                {
                    $toc[] = $zip->getNameIndex($i);
                }
            }

            natcasesort($toc);
            $toc = array_values($toc);
            $zip->close();
        }
    }
    else if (endsWith($archive, ".cbr"))
    {
        $rar = RarArchive::open($archive);
        if ($rar !== false)
        {
            $rar_entries = $rar->getEntries();
            for ($i = 0; $i < count($rar_entries); $i++)
            {
                if (endsWith($rar_entries[$i]->getName(), '.jpg') 
                    || endsWith($rar_entries[$i]->getName(), '.gif')
                    || endsWith($rar_entries[$i]->getName(), '.png'))
                {
                    $toc[] = $rar_entries[$i]->getName();
                }
            }

            natcasesort($toc);
            $toc = array_values($toc);
            $rar->close();
        }
    }
    
    return $toc;
}

/////////////////////////////////////////////////////////////////////////////////
// Passes an image from the archive directly to the browser; sets content-type header
// based on file type
function passImage($archive, $page)
{
    // Local browser cache
    $lastModified=filemtime($archive);
    $etagFile = md5_file(__FILE__);
    $ifModifiedSince=(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false);
    $etagHeader=(isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);
    header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModified)." GMT");
    header("Etag: $etagFile");
    header('Cache-Control: public');
    header("Expires: " . gmdate('D, d M Y H:i:s', strtotime("+1 year")) ." GMT");
    
    if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])==$lastModified || $etagHeader == $etagFile)
    {
        header("HTTP/1.1 304 Not Modified");
        exit;
    }

    if (endsWith($archive, ".cbz"))
    {
        $zip = new ZipArchive();
        if ($zip->open($archive))
        {
            if (endsWith($page, ".jpg"))
            {
                header('Content-type: image/jpeg');
                fpassthru($zip->getStream($page));
            }
            else if (endsWith($page, ".png"))
            {
                header('Content-type: image/png');
                fpassthru($zip->getStream($page));
            }
            else if (endsWith($page, ".gif"))
            {
                header('Content-type: image/gif');
                fpassthru($zip->getStream($page));
            }
            $zip->close();
        }
    }
    else if (endsWith($archive, ".cbr"))
    {
        $rar = RarArchive::open($archive);
        if ($rar !== false)
        {
            $rar_entries = $rar->getEntries();
            for ($i = 0; $i < count($rar_entries); $i++)
            {
                if ($rar_entries[$i]->getName() == $page)
                {
                    if (endsWith($page, ".jpg"))
                    {
                        header('Content-type: image/jpeg');
                        fpassthru($rar_entries[$i]->getStream());
                    }
                    else if (endsWith($page, ".png"))
                    {
                        header('Content-type: image/png');
                        fpassthru($rar_entries[$i]->getStream());
                    }
                    else if (endsWith($page, ".gif"))
                    {
                        header('Content-type: image/gif');
                        fpassthru($rar_entries[$i]->getStream());
                    }
                    $rar->close();
                    return;
                }
            }
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////
// If $haystack ends with $needle, return true; false otherwise; case insensitive
function endsWith($haystack, $needle)
{
    return $needle === "" || substr(strtolower($haystack), -strlen($needle)) === strtolower($needle);
}
?>