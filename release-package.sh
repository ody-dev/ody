#!/bin/bash
# release-package.sh

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 <package-name> <version>"
  exit 1
fi

PACKAGE=$1
VERSION=$2

# Validate the package exists
if [ ! -d "packages/$PACKAGE" ]; then
  echo "Package packages/$PACKAGE does not exist!"
  exit 1
fi

# Update version in composer.json
jq ".version = \"$VERSION\"" packages/$PACKAGE/composer.json > temp.json && mv temp.json packages/$PACKAGE/composer.json

# Update CHANGELOG.md if it exists
if [ -f "packages/$PACKAGE/CHANGELOG.md" ]; then
  sed -i "s/## Unreleased/## v$VERSION - $(date +%Y-%m-%d)/" packages/$PACKAGE/CHANGELOG.md
fi

# Commit changes
git add packages/$PACKAGE/composer.json
git add packages/$PACKAGE/CHANGELOG.md
git commit -m "chore(release): bump $PACKAGE to version $VERSION"

# Create a tag
git tag $VERSION

# Push changes
git push
git push --tags

echo "Package $PACKAGE released as version $VERSION"

# Automatically update dependencies
PACKAGES_DIR="packages"
for DEPENDENT in $(find $PACKAGES_DIR -name "composer.json" -not -path "$PACKAGES_DIR/$PACKAGE/*"); do
  # Check if this package depends on the one we're updating
  if grep -q "\"ody/$PACKAGE\"" "$DEPENDENT"; then
    jq ".require.\"ody/$PACKAGE\" = \"^$VERSION\"" "$DEPENDENT" > temp.json && mv temp.json "$DEPENDENT"
    git add "$DEPENDENT"
  fi
done