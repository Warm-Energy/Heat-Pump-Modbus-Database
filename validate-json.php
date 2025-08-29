#!/usr/bin/env php
<?php

/**
 * JSON Validator for Heat Pump Modbus Register Definitions
 * Validates all JSON files in the src directory against the expected schema
 */

class ModbusJSONValidator
{
    private array $errors = [];
    private array $warnings = [];
    private int $filesValidated = 0;
    
    // Required fields for basic structure
    private array $requiredFields = [
        'make' => 'string',
        'modbus' => 'array'
    ];
    
    // Required modbus fields
    private array $requiredModbusFields = [
        'models' => 'array',
        'connection' => 'array',
        'registers' => 'array'
    ];
    
    // Required connection fields
    private array $requiredConnectionFields = [
        'baudrate' => 'integer',
        'method' => 'string'
    ];
    
    // Common interface fields that should be marked
    private array $commonInterfaces = [
        'outdoor_temperature',
        'water_inlet_temp',
        'water_outlet_temp',
        'water_outlet_target',
        'flow_temp_control',
        'discharge_temp',
        'suction_temp',
        'operating_mode',
        'error_code',
        'compressor_status',
        'water_flow_rate'
    ];
    
    /**
     * Validate all JSON files
     */
    public function validateAll(): bool
    {
        echo "\nHeat Pump Modbus JSON Validator\n";
        echo "=====================================\n\n";
        
        $srcDir = __DIR__ . '/src';
        
        if (!is_dir($srcDir)) {
            $this->addError("Source directory 'src' not found");
            return false;
        }
        
        // Find all JSON files
        $jsonFiles = $this->findJsonFiles($srcDir);
        
        if (empty($jsonFiles)) {
            $this->addWarning("No JSON files found in src directory");
            return false;
        }
        
        // Validate each file
        foreach ($jsonFiles as $file) {
            $this->validateFile($file);
        }
        
        // Display results
        $this->displayResults();
        
        return empty($this->errors);
    }
    
    /**
     * Find all JSON files recursively
     */
    private function findJsonFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Validate a single JSON file
     */
    private function validateFile(string $filepath): void
    {
        $this->filesValidated++;
        $relativePath = str_replace(__DIR__ . '/', '', $filepath);
        
        echo "Validating: $relativePath\n";
        
        // Check if file is readable
        if (!is_readable($filepath)) {
            $this->addError("Cannot read file: $relativePath");
            return;
        }
        
        // Load and parse JSON
        $content = file_get_contents($filepath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError("Invalid JSON in $relativePath: " . json_last_error_msg());
            return;
        }
        
        // Validate structure
        $this->validateStructure($data, $relativePath);
        
        // Validate registers
        if (isset($data['modbus']['registers'])) {
            $this->validateRegisters($data['modbus']['registers'], $relativePath);
        }
        
        // Check for common interfaces
        $this->checkCommonInterfaces($data, $relativePath);
    }
    
    /**
     * Validate basic JSON structure
     */
    private function validateStructure(array $data, string $file): void
    {
        // Check required top-level fields
        foreach ($this->requiredFields as $field => $type) {
            if (!isset($data[$field])) {
                $this->addError("Missing required field '$field' in $file");
            } elseif (gettype($data[$field]) !== $type) {
                $this->addError("Field '$field' must be $type in $file");
            }
        }
        
        // Check modbus structure
        if (isset($data['modbus'])) {
            foreach ($this->requiredModbusFields as $field => $type) {
                if (!isset($data['modbus'][$field])) {
                    $this->addError("Missing required modbus.$field in $file");
                } elseif (gettype($data['modbus'][$field]) !== $type) {
                    $this->addError("Field modbus.$field must be $type in $file");
                }
            }
            
            // Check connection fields
            if (isset($data['modbus']['connection'])) {
                foreach ($this->requiredConnectionFields as $field => $type) {
                    if (!isset($data['modbus']['connection'][$field])) {
                        $this->addError("Missing required connection.$field in $file");
                    }
                }
                
                // Validate baudrate
                if (isset($data['modbus']['connection']['baudrate'])) {
                    $validBaudrates = [1200, 2400, 4800, 9600, 19200, 38400, 57600, 115200];
                    if (!in_array($data['modbus']['connection']['baudrate'], $validBaudrates)) {
                        $this->addWarning("Unusual baudrate in $file: " . $data['modbus']['connection']['baudrate']);
                    }
                }
            }
            
            // Check models array
            if (isset($data['modbus']['models']) && empty($data['modbus']['models'])) {
                $this->addError("Models array is empty in $file");
            }
        }
    }
    
    /**
     * Validate register definitions
     */
    private function validateRegisters(array $registers, string $file): void
    {
        $validRegisterTypes = ['sensors', 'switches', 'climates', 'input_registers', 
                               'holding_registers', 'coils', 'discrete_inputs'];
        
        foreach ($registers as $type => $items) {
            if (!in_array($type, $validRegisterTypes)) {
                $this->addWarning("Unknown register type '$type' in $file");
                continue;
            }
            
            if (!is_array($items)) {
                $this->addError("Register type '$type' must be an array in $file");
                continue;
            }
            
            foreach ($items as $index => $item) {
                // Check for required fields
                if (!isset($item['name'])) {
                    $this->addError("Missing 'name' for $type[$index] in $file");
                }
                
                if (!isset($item['address']) && !in_array($type, ['climates'])) {
                    $this->addError("Missing 'address' for $type[$index] in $file");
                }
                
                // Check address range
                if (isset($item['address'])) {
                    if (!is_numeric($item['address']) || $item['address'] < 0 || $item['address'] > 65535) {
                        $this->addError("Invalid address for {$item['name']} in $file");
                    }
                }
                
                // Check scale values
                if (isset($item['scale'])) {
                    if (!is_numeric($item['scale']) || $item['scale'] <= 0) {
                        $this->addWarning("Invalid scale for {$item['name']} in $file");
                    }
                }
                
                // Climate-specific validation
                if ($type === 'climates') {
                    if (!isset($item['target_temp_register'])) {
                        $this->addWarning("Climate {$item['name']} missing target_temp_register in $file");
                    }
                    if (!isset($item['max_temp']) || !isset($item['min_temp'])) {
                        $this->addWarning("Climate {$item['name']} missing temp limits in $file");
                    }
                }
            }
        }
    }
    
    /**
     * Check for common interface mappings
     */
    private function checkCommonInterfaces(array $data, string $file): void
    {
        $foundInterfaces = [];
        
        // Search all register types
        if (isset($data['modbus']['registers'])) {
            foreach ($data['modbus']['registers'] as $type => $items) {
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (isset($item['common_interface'])) {
                            $foundInterfaces[] = $item['common_interface'];
                        }
                    }
                }
            }
        }
        
        // Check for essential common interfaces
        $essentialInterfaces = ['outdoor_temperature', 'water_outlet_temp', 'operating_mode'];
        foreach ($essentialInterfaces as $interface) {
            if (!in_array($interface, $foundInterfaces)) {
                $this->addWarning("Missing common interface mapping for '$interface' in $file");
            }
        }
        
        // Report found interfaces
        if (!empty($foundInterfaces)) {
            echo "  [OK] Found " . count($foundInterfaces) . " common interface mappings\n";
        }
    }
    
    /**
     * Add error message
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
        echo "  [ERROR] $message\n";
    }
    
    /**
     * Add warning message
     */
    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
        echo "  [WARNING] $message\n";
    }
    
    /**
     * Display validation results
     */
    private function displayResults(): void
    {
        echo "\n=====================================\n";
        echo "Validation Results\n";
        echo "=====================================\n\n";
        
        echo "Files validated: {$this->filesValidated}\n";
        echo "Errors found: " . count($this->errors) . "\n";
        echo "Warnings found: " . count($this->warnings) . "\n\n";
        
        if (empty($this->errors)) {
            echo "[SUCCESS] All JSON files are valid!\n";
        } else {
            echo "[FAILED] Validation failed with " . count($this->errors) . " error(s)\n";
            echo "\nPlease fix the errors above before committing.\n";
        }
        
        if (!empty($this->warnings)) {
            echo "\n[WARNING] Consider addressing the warnings for better compatibility.\n";
        }
    }
}

// Run validator
$validator = new ModbusJSONValidator();
$success = $validator->validateAll();

exit($success ? 0 : 1);