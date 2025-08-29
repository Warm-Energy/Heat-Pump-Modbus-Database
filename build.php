#!/usr/bin/env php
<?php

/**
 * Unified build script to convert JSON modbus register definitions to multiple formats
 * Supports: Home Assistant, ESPHome
 */

require_once __DIR__ . '/build-esphome.php';

class ModbusBuildSystem
{
    private string $srcDir;
    private string $buildsDir;
    
    public function __construct(string $srcDir = 'src', string $buildsDir = 'builds')
    {
        $this->srcDir = $srcDir;
        $this->buildsDir = $buildsDir;
    }
    
    /**
     * Main build process
     */
    public function build(): void
    {
        echo "Starting unified build process...\n";
        echo "========================================\n\n";
        
        // Find all JSON files in src directory
        $manufacturers = $this->findManufacturers();
        
        foreach ($manufacturers as $manufacturer) {
            echo "Processing manufacturer: $manufacturer\n";
            echo "----------------------------------------\n";
            
            // Build Home Assistant configs
            echo "  Building Home Assistant configuration...\n";
            $this->processManufacturerHomeAssistant($manufacturer);
            
            // Build ESPHome configs
            echo "  Building ESPHome configuration...\n";
            $this->processManufacturerESPHome($manufacturer);
            
            echo "\n";
        }
        
        echo "========================================\n";
        echo "All builds complete!\n";
    }
    
    /**
     * Find all manufacturer directories in src
     */
    private function findManufacturers(): array
    {
        $manufacturers = [];
        
        if (!is_dir($this->srcDir)) {
            throw new Exception("Source directory '{$this->srcDir}' does not exist");
        }
        
        $dirs = scandir($this->srcDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            $path = $this->srcDir . '/' . $dir;
            if (is_dir($path)) {
                $manufacturers[] = $dir;
            }
        }
        
        return $manufacturers;
    }
    
    /**
     * Process a single manufacturer's JSON files for Home Assistant
     */
    private function processManufacturerHomeAssistant(string $manufacturer): void
    {
        $srcPath = $this->srcDir . '/' . $manufacturer;
        $jsonFiles = glob($srcPath . '/*.json');
        
        foreach ($jsonFiles as $jsonFile) {
            echo "  - Processing: " . basename($jsonFile) . "\n";
            
            $data = $this->loadJsonFile($jsonFile);
            $yaml = $this->convertToHomeAssistantYaml($data);
            
            // Create output directory for Home Assistant
            $outputDir = $this->buildsDir . '/' . $manufacturer . '/homeassistant';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            // Write main modbus YAML file
            $outputFile = $outputDir . '/' . $manufacturer . '-modbus.yaml';
            $this->writeYamlFile($outputFile, $yaml);
            
            echo "    - Modbus config: $outputFile\n";
            
            // Write unlock registers automation if available
            if (isset($data['modbus']['unlock_registers'])) {
                $unlockFile = $outputDir . '/' . $manufacturer . '-register-unlock-automation.yaml';
                $unlockYaml = $this->processUnlockRegisters($data['modbus']['unlock_registers']);
                $this->writeYamlFile($unlockFile, $unlockYaml);
                echo "    - Unlock automation: $unlockFile\n";
            }
        }
    }
    
    /**
     * Process a single manufacturer's JSON files for ESPHome
     */
    private function processManufacturerESPHome(string $manufacturer): void
    {
        $srcPath = $this->srcDir . '/' . $manufacturer;
        $jsonFiles = glob($srcPath . '/*.json');
        
        foreach ($jsonFiles as $jsonFile) {
            $data = $this->loadJsonFile($jsonFile);
            
            // Create ESPHome builder instance
            $espBuilder = new ModbusBuildESPHome($this->srcDir, $this->buildsDir);
            
            // Convert to ESPHome format
            $yaml = $espBuilder->convertToESPHomeYamlPublic($data);
            
            // Create output directory for ESPHome
            $outputDir = $this->buildsDir . '/' . $manufacturer . '/esphome';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            // Write YAML file
            $outputFile = $outputDir . '/' . $manufacturer . '-modbus.yaml';
            $this->writeESPHomeYamlFile($outputFile, $yaml);
            
            echo "    - ESPHome config: $outputFile\n";
        }
    }
    
    /**
     * Load and parse JSON file
     */
    private function loadJsonFile(string $file): array
    {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON file: $file - " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Convert JSON data to Home Assistant YAML format
     */
    private function convertToHomeAssistantYaml(array $data): array
    {
        $yaml = [];
        
        // Create modbus configuration with defaults
        $modbusConfig = [
            'name' => $data['make'] ?? 'unknown',
            'type' => 'serial',
            'port' => '/dev/ttyUSB0'
        ];
        
        // Load connection settings dynamically from JSON if available
        if (isset($data['modbus']['connection'])) {
            $conn = $data['modbus']['connection'];
            $modbusConfig['baudrate'] = $conn['baudrate'] ?? 9600;
            $modbusConfig['bytesize'] = $conn['bytesize'] ?? 8;
            $modbusConfig['method'] = $conn['method'] ?? 'rtu';
            $modbusConfig['parity'] = $conn['parity'] ?? 'N';
            $modbusConfig['stopbits'] = $conn['stopbits'] ?? 1;
            $modbusConfig['delay'] = $conn['delay'] ?? 0;
            $modbusConfig['message_wait_milliseconds'] = $conn['message_wait_milliseconds'] ?? 30;
            $modbusConfig['timeout'] = $conn['timeout'] ?? 5;
        } else {
            // Use defaults if no connection settings in JSON
            $modbusConfig['baudrate'] = 9600;
            $modbusConfig['bytesize'] = 8;
            $modbusConfig['method'] = 'rtu';
            $modbusConfig['parity'] = 'E';
            $modbusConfig['stopbits'] = 1;
            $modbusConfig['delay'] = 0;
            $modbusConfig['message_wait_milliseconds'] = 30;
            $modbusConfig['timeout'] = 5;
        }
        
        // Process switches
        if (isset($data['modbus']['registers']['switches'])) {
            $modbusConfig['switches'] = $this->processSwitches($data['modbus']['registers']['switches']);
        }
        
        // Process sensors
        if (isset($data['modbus']['registers']['sensors'])) {
            $modbusConfig['sensors'] = $this->processSensors($data['modbus']['registers']['sensors']);
        }
        
        // Process climates
        if (isset($data['modbus']['registers']['climates'])) {
            $modbusConfig['climates'] = $this->processClimates($data['modbus']['registers']['climates']);
        }
        
        $yaml[] = $modbusConfig;
        
        return $yaml;
    }
    
    /**
     * Process switches for Home Assistant
     */
    private function processSwitches(array $switches): array
    {
        $result = [];
        
        foreach ($switches as $switch) {
            $switchConfig = [
                'name' => $switch['name'],
                'slave' => 2,  // Default slave ID
                'address' => $switch['address'],
                'write_type' => $switch['write_type'] ?? 'holding'
            ];
            
            if (isset($switch['command_on'])) {
                $switchConfig['command_on'] = $switch['command_on'];
            }
            
            if (isset($switch['command_off'])) {
                $switchConfig['command_off'] = $switch['command_off'];
            }
            
            if (isset($switch['verify'])) {
                $switchConfig['verify'] = $switch['verify'];
            }
            
            // Generate unique_id from name
            $switchConfig['unique_id'] = $switch['name'];
            
            $result[] = $switchConfig;
        }
        
        return $result;
    }
    
    /**
     * Process sensors for Home Assistant
     */
    private function processSensors(array $sensors): array
    {
        $result = [];
        
        foreach ($sensors as $sensor) {
            $sensorConfig = [
                'name' => $sensor['name'],
                'slave' => 2,  // Default slave ID
                'address' => $sensor['address']
            ];
            
            // Add optional fields
            if (isset($sensor['unit'])) {
                $sensorConfig['unit_of_measurement'] = $sensor['unit'];
            }
            
            if (isset($sensor['scale'])) {
                $sensorConfig['scale'] = $sensor['scale'];
            }
            
            if (isset($sensor['scan_interval'])) {
                $sensorConfig['scan_interval'] = $sensor['scan_interval'];
            }
            
            if (isset($sensor['precision'])) {
                $sensorConfig['precision'] = $sensor['precision'];
            }
            
            // Generate unique_id from name
            $sensorConfig['unique_id'] = $sensor['name'];
            
            $result[] = $sensorConfig;
        }
        
        return $result;
    }
    
    /**
     * Process climate entities for Home Assistant
     */
    private function processClimates(array $climates): array
    {
        $result = [];
        
        foreach ($climates as $climate) {
            $climateConfig = [
                'name' => $climate['name'],
                'slave' => 2,  // Default slave ID
                'address' => $climate['address']
            ];
            
            // Add optional fields
            if (isset($climate['scale'])) {
                $climateConfig['scale'] = $climate['scale'];
            }
            
            if (isset($climate['max_temp'])) {
                $climateConfig['max_temp'] = $climate['max_temp'];
            }
            
            if (isset($climate['min_temp'])) {
                $climateConfig['min_temp'] = $climate['min_temp'];
            }
            
            if (isset($climate['scan_interval'])) {
                $climateConfig['scan_interval'] = $climate['scan_interval'];
            }
            
            if (isset($climate['precision'])) {
                $climateConfig['precision'] = $climate['precision'];
            }
            
            if (isset($climate['target_temp_register'])) {
                $climateConfig['target_temp_register'] = $climate['target_temp_register'];
            }
            
            if (isset($climate['hvac_onoff_register'])) {
                $climateConfig['hvac_onoff_register'] = $climate['hvac_onoff_register'];
            }
            
            if (isset($climate['hvac_mode_register'])) {
                $climateConfig['hvac_mode_register'] = $climate['hvac_mode_register'];
            }
            
            // Generate unique_id from name
            $climateConfig['unique_id'] = $climate['name'];
            
            $result[] = $climateConfig;
        }
        
        return $result;
    }
    
    /**
     * Process unlock registers for documentation
     */
    private function processUnlockRegisters(array $unlockRegisters): array
    {
        $result = [];
        
        if (isset($unlockRegisters['description'])) {
            $result['description'] = $unlockRegisters['description'];
        }
        
        $result['commands'] = [];
        
        foreach ($unlockRegisters as $key => $register) {
            if ($key === 'description') continue;
            
            if (isset($register['address']) && isset($register['values'])) {
                $command = [
                    'service' => 'modbus.write_register',
                    'data' => [
                        'hub' => 'samsung',
                        'address' => $register['address'],
                        'values' => []
                    ]
                ];
                
                foreach ($register['values'] as $value) {
                    $valueEntry = sprintf("%s #%d %s", 
                        $value['hex'], 
                        $value['decimal'], 
                        $value['description']
                    );
                    $command['data']['values'][] = $valueEntry;
                }
                
                $result['commands'][] = $command;
            }
        }
        
        return $result;
    }
    
    /**
     * Write YAML file
     */
    private function writeYamlFile(string $file, array $data): void
    {
        $yamlContent = $this->arrayToYaml($data);
        file_put_contents($file, $yamlContent);
    }
    
    /**
     * Convert array to YAML format (simplified YAML generator)
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                // Array item
                if (is_array($value)) {
                    // Check if this is an array with a 'name' key to format inline
                    if (isset($value['name'])) {
                        $yaml .= $prefix . "- name: " . $this->formatValue($value['name']) . "\n";
                        // Process remaining keys
                        foreach ($value as $subKey => $subValue) {
                            if ($subKey !== 'name') {
                                if (is_array($subValue)) {
                                    if ($this->isSequentialArray($subValue)) {
                                        $yaml .= $prefix . "  " . $subKey . ":\n";
                                        foreach ($subValue as $item) {
                                            $yaml .= $prefix . "    - ";
                                            if (is_array($item)) {
                                                $yaml .= "\n" . $this->arrayToYaml($item, $indent + 3);
                                            } else {
                                                $yaml .= $this->formatValue($item) . "\n";
                                            }
                                        }
                                    } else {
                                        $yaml .= $prefix . "  " . $subKey . ":\n";
                                        $yaml .= $this->arrayToYaml($subValue, $indent + 2);
                                    }
                                } else {
                                    $yaml .= $prefix . "  " . $subKey . ": " . $this->formatValue($subValue) . "\n";
                                }
                            }
                        }
                    } else {
                        $yaml .= $prefix . "- ";
                        $yaml .= "\n" . $this->arrayToYaml($value, $indent + 1);
                    }
                } else {
                    $yaml .= $prefix . "- " . $this->formatValue($value) . "\n";
                }
            } else {
                // Key-value pair
                if (is_array($value)) {
                    if ($this->isSequentialArray($value)) {
                        // Sequential array
                        $yaml .= $prefix . $key . ":\n";
                        foreach ($value as $item) {
                            if (is_array($item) && isset($item['name'])) {
                                // Format with name inline
                                $yaml .= $prefix . "  - name: " . $this->formatValue($item['name']) . "\n";
                                foreach ($item as $subKey => $subValue) {
                                    if ($subKey !== 'name') {
                                        if (is_array($subValue)) {
                                            if ($this->isSequentialArray($subValue)) {
                                                $yaml .= $prefix . "    " . $subKey . ":\n";
                                                foreach ($subValue as $subItem) {
                                                    $yaml .= $prefix . "      - ";
                                                    if (is_array($subItem)) {
                                                        $yaml .= "\n" . $this->arrayToYaml($subItem, $indent + 4);
                                                    } else {
                                                        $yaml .= $this->formatValue($subItem) . "\n";
                                                    }
                                                }
                                            } else {
                                                $yaml .= $prefix . "    " . $subKey . ":\n";
                                                $yaml .= $this->arrayToYaml($subValue, $indent + 3);
                                            }
                                        } else {
                                            $yaml .= $prefix . "    " . $subKey . ": " . $this->formatValue($subValue) . "\n";
                                        }
                                    }
                                }
                            } else {
                                $yaml .= $prefix . "  - ";
                                if (is_array($item)) {
                                    $yaml .= "\n" . $this->arrayToYaml($item, $indent + 2);
                                } else {
                                    $yaml .= $this->formatValue($item) . "\n";
                                }
                            }
                        }
                    } else {
                        // Associative array
                        $yaml .= $prefix . $key . ":\n";
                        $yaml .= $this->arrayToYaml($value, $indent + 1);
                    }
                } else {
                    $yaml .= $prefix . $key . ": " . $this->formatValue($value) . "\n";
                }
            }
        }
        
        return $yaml;
    }
    
    /**
     * Check if array is sequential (numeric keys)
     */
    private function isSequentialArray(array $arr): bool
    {
        if (empty($arr)) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
    
    /**
     * Format a single value for YAML
     */
    private function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_string($value)) {
            // Quote strings if they contain special characters
            if (preg_match('/[:\{\}\[\],&\*#\?|\-<>=!%@\\\\]/', $value) || 
                $value === '' || 
                trim($value) !== $value) {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        }
        return (string)$value;
    }
    
    /**
     * Write ESPHome YAML file with proper header
     */
    private function writeESPHomeYamlFile(string $file, array $data): void
    {
        $yamlContent = $this->arrayToYaml($data);
        
        // Add header comments
        $header = "# ESPHome configuration for heat pump modbus interface\n";
        $header .= "# Generated from modbus register definitions\n";
        $header .= "# Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        file_put_contents($file, $header . $yamlContent);
    }
}

// Only run if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    try {
        $builder = new ModbusBuildSystem();
        $builder->build();
    } catch (Exception $e) {
        echo "Build failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}