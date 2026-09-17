#!/bin/bash
cd /Users/f/Sites/schach/javascript

for file in *.js; do
  sed -i.bak "s/\\\\\'/'/g; s/\\\\\"/\"/g" "$file"
  rm "${file}.bak"
  echo "Processed: $file"
done

echo "Done!"
