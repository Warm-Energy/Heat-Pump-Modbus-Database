#!/bin/bash

# Modbus Register Database Build Script
# Builds all configurations from JSON source files

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Function to print colored output
print_header() {
    echo -e "${BOLD}${BLUE}=============================================================${NC}"
    echo -e "${BOLD}${BLUE}         Modbus Register Database Build System${NC}"
    echo -e "${BOLD}${BLUE}=============================================================${NC}"
    echo
}

print_section() {
    echo -e "${BOLD}${GREEN}> $1${NC}"
}

print_info() {
    echo -e "${YELLOW}  INFO: $1${NC}"
}

print_success() {
    echo -e "${GREEN}  [OK] $1${NC}"
}

print_error() {
    echo -e "${RED}  [ERROR] $1${NC}"
}

# Check if PHP is installed
check_requirements() {
    print_section "Checking requirements..."
    
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed. Please install PHP 7.4 or higher."
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_success "PHP $PHP_VERSION found"
    echo
}

# Clean previous builds
clean_builds() {
    if [ "$1" == "--clean" ] || [ "$2" == "--clean" ]; then
        print_section "Cleaning previous builds..."
        if [ -d "builds" ]; then
            rm -rf builds/*
            print_success "Previous builds cleaned"
        else
            print_info "No previous builds to clean"
        fi
        echo
    fi
}

# Run the main build
run_build() {
    print_section "Building configurations..."
    echo
    
    # Run the unified PHP build script
    if [ -f "build.php" ]; then
        php build.php
        BUILD_EXIT_CODE=$?
        
        if [ $BUILD_EXIT_CODE -eq 0 ]; then
            echo
            print_success "Build completed successfully!"
        else
            print_error "Build failed with exit code $BUILD_EXIT_CODE"
            exit $BUILD_EXIT_CODE
        fi
    else
        print_error "build.php not found in current directory"
        exit 1
    fi
}

# Display build summary
show_summary() {
    echo
    print_section "Build Summary"
    
    if [ -d "builds" ]; then
        # Count manufacturers
        MANUFACTURER_COUNT=$(find builds -maxdepth 1 -type d ! -path builds | wc -l | tr -d ' ')
        print_info "Manufacturers processed: $MANUFACTURER_COUNT"
        
        # List what was built
        for manufacturer in builds/*/; do
            if [ -d "$manufacturer" ]; then
                MANUFACTURER_NAME=$(basename "$manufacturer")
                print_info "Built for $MANUFACTURER_NAME:"
                
                # Check Home Assistant builds
                if [ -d "$manufacturer/homeassistant" ]; then
                    HA_COUNT=$(find "$manufacturer/homeassistant" -name "*.yaml" | wc -l | tr -d ' ')
                    echo -e "    ${GREEN}-${NC} Home Assistant: $HA_COUNT files"
                fi
                
                # Check ESPHome builds
                if [ -d "$manufacturer/esphome" ]; then
                    ESP_COUNT=$(find "$manufacturer/esphome" -name "*.yaml" | wc -l | tr -d ' ')
                    echo -e "    ${GREEN}-${NC} ESPHome: $ESP_COUNT files"
                fi
            fi
        done
    else
        print_error "No builds directory found"
    fi
}

# Display help
show_help() {
    echo "Usage: $0 [OPTIONS]"
    echo
    echo "Options:"
    echo "  --clean    Clean previous builds before building"
    echo "  --help     Display this help message"
    echo "  --verbose  Show detailed output during build"
    echo
    echo "This script builds modbus configurations for various platforms:"
    echo "  - Home Assistant YAML configurations"
    echo "  - ESPHome device configurations"
    echo
    echo "Source files are read from: src/"
    echo "Output files are written to: builds/"
    echo
    echo "Directory structure:"
    echo "  builds/"
    echo "    └── <manufacturer>/"
    echo "        ├── homeassistant/"
    echo "        │   ├── <manufacturer>-modbus.yaml"
    echo "        │   └── <manufacturer>-register-unlock-automation.yaml"
    echo "        └── esphome/"
    echo "            └── <manufacturer>-modbus.yaml"
}

# Main execution
main() {
    # Check for help flag
    if [ "$1" == "--help" ] || [ "$1" == "-h" ]; then
        show_help
        exit 0
    fi
    
    # Clear screen for better visibility (optional)
    # clear
    
    print_header
    
    # Check requirements
    check_requirements
    
    # Clean if requested
    clean_builds "$@"
    
    # Run the build
    run_build
    
    # Show summary
    show_summary
    
    echo
    echo -e "${BOLD}${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BOLD}${GREEN}All builds completed successfully!${NC}"
    echo -e "${BOLD}${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo
    
    # Show where files are
    echo -e "${BOLD}Output files location:${NC}"
    echo -e "  ${BLUE}builds/${NC}"
    echo
}

# Run main function with all arguments
main "$@"