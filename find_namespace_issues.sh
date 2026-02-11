#!/bin/bash
# Find PHP files where namespace is not on line 3 or 4 (after <?php and optional blank line)

find app/ -name "*.php" -type f | while read file; do
    # Get first 10 lines
    head -10 "$file" > /tmp/check.txt
    
    # Check if namespace is on line 3 or 4
    line3=$(sed -n '3p' "$file")
    line4=$(sed -n '4p' "$file")
    
    # If neither line 3 nor 4 starts with "namespace", report it
    if [[ ! "$line3" =~ ^namespace ]] && [[ ! "$line4" =~ ^namespace ]]; then
        # But only if the file actually has a namespace
        if grep -q "^namespace" "$file"; then
            echo "=== $file ==="
            head -10 "$file"
            echo ""
        fi
    fi
done
