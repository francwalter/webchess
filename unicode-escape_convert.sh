#!/bin/bash
cd /Users/f/Sites/schach/javascript

for file in *.js; do
  sed -i '' \
    -e 's/\\u003d/=/g' \
    -e 's/\\u0027/'\''/g' \
    -e 's/\\u003c/</g' \
    -e 's/\\u003e/>/g' \
    -e 's/\\u0026/\&/g' \
    -e 's/\\u002b/+/g' \
    -e 's/\\u002d/-/g' \
    -e 's/\\u002f/\//g' \
    -e 's/\\u003a/:/g' \
    -e 's/\\u003b/;/g' \
    -e 's/\\u0028/(/g' \
    -e 's/\\u0029/)/g' \
    -e 's/\\u005b/[/g' \
    -e 's/\\u005d/]/g' \
    -e 's/\\u007b/{/g' \
    -e 's/\\u007d/}/g' \
    "$file"
  echo "Processed: $file"
done

echo "Done! All Unicode escapes converted."
