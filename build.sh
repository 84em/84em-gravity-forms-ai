#!/bin/bash

# 84EM Gravity Forms AI Analysis Build Script
# Builds minified CSS and JS files with source maps and creates installable ZIP

set -e

echo "🔨 Building 84EM Gravity Forms AI Analysis Plugin..."

# Configuration
PLUGIN_NAME="84em-gravity-forms-ai"
BUILD_DIR="build"
VERSION=$(grep "Version:" 84em-gravity-forms-ai.php | awk '{print $3}')

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install Node.js and npm first."
    exit 1
fi

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Clean old build files
echo "🧹 Cleaning old build files..."
npm run clean
rm -rf "$BUILD_DIR"
rm -f "${PLUGIN_NAME}.zip"
rm -f "${PLUGIN_NAME}-*.zip"

# Build CSS
echo "🎨 Minifying CSS..."
npm run build:css

# Build JS
echo "📜 Minifying JavaScript..."
npm run build:js

# Check if files were created
if [ ! -f "assets/css/admin.min.css" ] || [ ! -f "assets/js/admin.min.js" ]; then
    echo "❌ Build failed - minified files not created"
    exit 1
fi

# Create build directory
echo "📁 Creating build directory..."
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

# Copy necessary files
echo "📋 Copying plugin files..."
# Copy assets but exclude source files and maps
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/assets/css"
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/assets/js"
cp assets/css/*.min.css "$BUILD_DIR/$PLUGIN_NAME/assets/css/" 2>/dev/null || true
cp assets/js/*.min.js "$BUILD_DIR/$PLUGIN_NAME/assets/js/" 2>/dev/null || true

# Copy other directories
cp -r includes "$BUILD_DIR/$PLUGIN_NAME/"
cp -r languages "$BUILD_DIR/$PLUGIN_NAME/" 2>/dev/null || mkdir "$BUILD_DIR/$PLUGIN_NAME/languages"
cp 84em-gravity-forms-ai.php "$BUILD_DIR/$PLUGIN_NAME/"
cp README.md "$BUILD_DIR/$PLUGIN_NAME/"
cp CHANGELOG.md "$BUILD_DIR/$PLUGIN_NAME/"

# Create ZIP file
echo "📦 Creating ZIP archive..."
cd "$BUILD_DIR"
zip -r "../${PLUGIN_NAME}-${VERSION}.zip" "$PLUGIN_NAME" -q
cd ..

# Also create a latest version
cp "${PLUGIN_NAME}-${VERSION}.zip" "${PLUGIN_NAME}.zip"

# Clean up build directory
rm -rf "$BUILD_DIR"

# Final output
echo ""
echo "✅ Build complete!"
echo ""
echo "📦 Plugin packages created:"
echo "  - ${PLUGIN_NAME}-${VERSION}.zip (versioned)"
echo "  - ${PLUGIN_NAME}.zip (latest)"
echo ""
echo "File sizes:"
ls -lh "${PLUGIN_NAME}-${VERSION}.zip" | awk '{print "  - Versioned: " $5}'
ls -lh "${PLUGIN_NAME}.zip" | awk '{print "  - Latest: " $5}'
echo ""
echo "Plugin Details:"
echo "  - Version: ${VERSION}"
echo "  - Name: 84EM Gravity Forms AI Analysis"
echo ""
echo "To install:"
echo "  1. Go to WordPress Admin → Plugins → Add New"
echo "  2. Click 'Upload Plugin'"
echo "  3. Choose ${PLUGIN_NAME}-${VERSION}.zip"
echo "  4. Click 'Install Now' and activate"
echo ""
echo "Development files:"
echo "  - CSS: assets/css/admin.min.css (with source map)"
echo "  - JS: assets/js/admin.min.js (with source map)"
