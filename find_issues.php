<?php

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('app')
);

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() != 'php') {
        continue;
    }
    
    $path = $file->getPathname();
    $lines = file($path);
    
    if (count($lines) < 4) {
        continue;
    }
    
    // Check if line 4 (index 3) has namespace
    if (isset($lines[3]) && strpos(trim($lines[3]), 'namespace') === 0) {
        // Check if lines 2 or 3 have content other than blank or comments
        $line2 = trim($lines[1]);
        $line3 = trim($lines[2]);
        
        if (!empty($line2) && $line2 !== '' || (!empty($line3) && $line3 !== '')) {
            echo "FOUND: $path\n";
            for ($i = 0; $i < min(8, count($lines)); $i++) {
                echo ($i + 1) . ": " . $lines[$i];
            }
            echo "---\n\n";
        }
    }
}
