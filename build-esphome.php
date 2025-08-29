#!/usr/bin/env php
<?php

/**
 * Build script to convert JSON modbus register definitions to ESPHome YAML format
 */

class ModbusBuildESPHome
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
        echo "Starting ESPHome build process...\n";
        
        // Find all JSON files in src directory
        $manufacturers = $this->findManufacturers();
        
        foreach ($manufacturers as $manufacturer) {
            echo "Processing manufacturer: $manufacturer\n";
            $this->processManufacturer($manufacturer);
        }
        
        echo "ESPHome build complete!\n";
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
     * Process a single manufacturer's JSON files
     */
    private function processManufacturer(string $manufacturer): void
    {
        $srcPath = $this->srcDir . '/' . $manufacturer;
        $jsonFiles = glob($srcPath . '/*.json');
        
        foreach ($jsonFiles as $jsonFile) {
            echo "  - Processing: " . basename($jsonFile) . "\n";
            
            $data = $this->loadJsonFile($jsonFile);
            $yaml = $this->convertToESPHomeYaml($data);
            
            // Create output directory
            $outputDir = $this->buildsDir . '/' . $manufacturer;
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            // Write YAML file
            $outputFile = $outputDir . '/modbus-esphome.yaml';
            $this->writeYamlFile($outputFile, $yaml);
            
            echo "  - Written to: $outputFile\n";
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
     * Convert JSON data to ESPHome YAML format (public for unified build)
     */
    public function convertToESPHomeYamlPublic(array $data): array
    {
        return $this->convertToESPHomeYaml($data);
    }
    
    /**
     * Convert JSON data to ESPHome YAML format
     */
    private function convertToESPHomeYaml(array $data): array
    {
        $yaml = [];
        
        // ESPHome base configuration
        $yaml['esphome'] = [
            'name' => strtolower($data['make'] ?? 'heatpump') . '-modbus',
            'platform' => 'ESP32',
            'board' => 'esp32dev'
        ];
        
        // WiFi configuration placeholder
        $yaml['wifi'] = [
            'ssid' => '!secret wifi_ssid',
            'password' => '!secret wifi_password',
            'ap' => [
                'ssid' => 'HeatPump-Fallback',
                'password' => '!secret ap_password'
            ]
        ];
        
        // Enable logging
        $yaml['logger'] = [
            'level' => 'DEBUG',
            'baud_rate' => 0  // Disable UART logging if using for Modbus
        ];
        
        // Enable Home Assistant API
        $yaml['api'] = [
            'encryption' => [
                'key' => '!secret api_encryption_key'
            ]
        ];
        
        // OTA updates
        $yaml['ota'] = [
            'platform' => 'esphome',
            'password' => '!secret ota_password'
        ];
        
        // Web server (optional)
        $yaml['web_server'] = [
            'port' => 80
        ];
        
        // UART configuration for Modbus
        $conn = $data['modbus']['connection'] ?? [];
        $yaml['uart'] = [
            'id' => 'mod_bus',
            'tx_pin' => 'GPIO17',  // Example pins, should be configurable
            'rx_pin' => 'GPIO16',
            'baud_rate' => $conn['baudrate'] ?? 9600,
            'stop_bits' => $conn['stopbits'] ?? 1
        ];
        
        // Add parity if specified
        if (isset($conn['parity'])) {
            $parityMap = ['E' => 'EVEN', 'O' => 'ODD', 'N' => 'NONE'];
            $yaml['uart']['parity'] = $parityMap[$conn['parity']] ?? 'NONE';
        }
        
        // Modbus configuration
        $yaml['modbus'] = [
            'id' => 'modbus1',
            'uart_id' => 'mod_bus'
        ];
        
        // Modbus controller
        $yaml['modbus_controller'] = [
            'id' => 'heatpump',
            'address' => 2,  // Default slave address
            'modbus_id' => 'modbus1',
            'setup_priority' => -10,
            'update_interval' => '30s'
        ];
        
        // Process switches
        if (isset($data['modbus']['registers']['switches'])) {
            $yaml['switch'] = $this->processESPHomeSwitches($data['modbus']['registers']['switches']);
        }
        
        // Process sensors
        if (isset($data['modbus']['registers']['sensors'])) {
            $yaml['sensor'] = $this->processESPHomeSensors($data['modbus']['registers']['sensors']);
        }
        
        // Process climate entities
        if (isset($data['modbus']['registers']['climates'])) {
            $yaml['climate'] = $this->processESPHomeClimates($data['modbus']['registers']['climates']);
        }
        
        return $yaml;
    }
    
    /**
     * Process switches for ESPHome
     */
    private function processESPHomeSwitches(array $switches): array
    {
        $result = [];
        
        foreach ($switches as $switch) {
            $switchConfig = [
                'platform' => 'modbus_controller',
                'modbus_controller_id' => 'heatpump',
                'name' => $this->humanizeName($switch['name']),
                'id' => strtolower($switch['name']),
                'register_type' => $switch['write_type'] ?? 'holding',
                'address' => $switch['address'],
                'bitmask' => 1  // Default bitmask
            ];
            
            // Add icon based on name
            $switchConfig['icon'] = $this->getIconForEntity($switch['name']);
            
            $result[] = $switchConfig;
        }
        
        return $result;
    }
    
    /**
     * Process sensors for ESPHome
     */
    private function processESPHomeSensors(array $sensors): array
    {
        $result = [];
        
        foreach ($sensors as $sensor) {
            $sensorConfig = [
                'platform' => 'modbus_controller',
                'modbus_controller_id' => 'heatpump',
                'name' => $this->humanizeName($sensor['name']),
                'id' => strtolower($sensor['name']),
                'register_type' => 'holding',
                'address' => $sensor['address'],
                'value_type' => 'U_WORD'  // Default to unsigned word
            ];
            
            // Add unit of measurement
            if (isset($sensor['unit'])) {
                $sensorConfig['unit_of_measurement'] = $sensor['unit'];
                $sensorConfig['device_class'] = $this->getDeviceClass($sensor['unit']);
                $sensorConfig['state_class'] = 'measurement';
            }
            
            // Add scale/multiplier
            if (isset($sensor['scale'])) {
                $sensorConfig['filters'] = [
                    ['multiply' => $sensor['scale']]
                ];
            }
            
            // Add accuracy decimals
            if (isset($sensor['precision'])) {
                $sensorConfig['accuracy_decimals'] = $sensor['precision'];
            }
            
            // Add icon
            $sensorConfig['icon'] = $this->getIconForEntity($sensor['name'], $sensor['unit'] ?? '');
            
            // Update interval if different from default
            if (isset($sensor['scan_interval'])) {
                $sensorConfig['skip_updates'] = max(1, intval(30 / $sensor['scan_interval']));
            }
            
            $result[] = $sensorConfig;
        }
        
        return $result;
    }
    
    /**
     * Process climate entities for ESPHome
     */
    private function processESPHomeClimates(array $climates): array
    {
        $result = [];
        
        foreach ($climates as $climate) {
            // ESPHome climate through modbus is complex, create template climate
            $climateConfig = [
                'platform' => 'thermostat',
                'name' => $this->humanizeName($climate['name']),
                'id' => strtolower($climate['name']),
                'sensor' => strtolower($climate['name']) . '_temp',
                'min_temperature' => $climate['min_temp'] ?? 10,
                'max_temperature' => $climate['max_temp'] ?? 30,
                'temperature_step' => 0.5
            ];
            
            // Define heat action
            if (isset($climate['hvac_mode_register'])) {
                $climateConfig['heat_action'] = [
                    'switch.turn_on' => strtolower($climate['name']) . '_heat'
                ];
                $climateConfig['idle_action'] = [
                    'switch.turn_off' => strtolower($climate['name']) . '_heat'
                ];
            }
            
            $result[] = $climateConfig;
        }
        
        return $result;
    }
    
    /**
     * Convert snake_case names to human readable
     */
    private function humanizeName(string $name): string
    {
        // Remove EHS_ prefix if present
        $name = preg_replace('/^EHS_/', '', $name);
        // Replace underscores with spaces
        $name = str_replace('_', ' ', $name);
        // Capitalize words
        return ucwords(strtolower($name));
    }
    
    /**
     * Get device class based on unit
     */
    private function getDeviceClass(string $unit): string
    {
        $deviceClasses = [
            '°C' => 'temperature',
            '°F' => 'temperature',
            'A' => 'current',
            'V' => 'voltage',
            'W' => 'power',
            'kW' => 'power',
            'Hz' => 'frequency',
            '%' => 'power_factor',
            'l/min' => 'water',
            'rpm' => 'speed',
            'kgfcm2' => 'pressure'
        ];
        
        return $deviceClasses[$unit] ?? 'measurement';
    }
    
    /**
     * Get icon for entity based on name and unit
     */
    private function getIconForEntity(string $name, string $unit = ''): string
    {
        // Temperature sensors
        if (strpos(strtolower($name), 'temp') !== false || $unit === '°C') {
            return 'mdi:thermometer';
        }
        
        // Pressure sensors
        if (strpos(strtolower($name), 'pressure') !== false) {
            return 'mdi:gauge';
        }
        
        // Fan sensors
        if (strpos(strtolower($name), 'fan') !== false) {
            return 'mdi:fan';
        }
        
        // Water flow
        if (strpos(strtolower($name), 'flow') !== false || $unit === 'l/min') {
            return 'mdi:water-flow';
        }
        
        // Pump
        if (strpos(strtolower($name), 'pump') !== false) {
            return 'mdi:pump';
        }
        
        // Valve
        if (strpos(strtolower($name), 'valve') !== false) {
            return 'mdi:pipe-valve';
        }
        
        // Heater
        if (strpos(strtolower($name), 'heat') !== false) {
            return 'mdi:radiator';
        }
        
        // Compressor
        if (strpos(strtolower($name), 'compressor') !== false) {
            return 'mdi:air-conditioner';
        }
        
        // Error/status
        if (strpos(strtolower($name), 'error') !== false) {
            return 'mdi:alert-circle';
        }
        
        // Current
        if ($unit === 'A') {
            return 'mdi:current-ac';
        }
        
        // Voltage
        if ($unit === 'V') {
            return 'mdi:flash';
        }
        
        // Frequency
        if ($unit === 'Hz') {
            return 'mdi:sine-wave';
        }
        
        // Default
        return 'mdi:information';
    }
    
    /**
     * Write YAML file
     */
    private function writeYamlFile(string $file, array $data): void
    {
        $yamlContent = $this->arrayToYaml($data);
        
        // Add header comments
        $header = "# ESPHome configuration for heat pump modbus interface\n";
        $header .= "# Generated from modbus register definitions\n";
        $header .= "# Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        file_put_contents($file, $header . $yamlContent);
    }
    
    /**
     * Convert array to YAML format
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                // Array item
                if (is_array($value)) {
                    // For ESPHome configs, use inline format for better readability
                    if (isset($value['platform'])) {
                        $yaml .= $prefix . "- platform: " . $value['platform'] . "\n";
                        foreach ($value as $subKey => $subValue) {
                            if ($subKey !== 'platform') {
                                if (is_array($subValue)) {
                                    $yaml .= $prefix . "  " . $subKey . ":\n";
                                    $yaml .= $this->arrayToYaml($subValue, $indent + 2);
                                } else {
                                    $yaml .= $prefix . "  " . $subKey . ": " . $this->formatValue($subValue) . "\n";
                                }
                            }
                        }
                    } else {
                        $yaml .= $prefix . "- ";
                        if (count($value) === 1 && !$this->isAssoc($value)) {
                            $yaml .= $this->formatValue(reset($value)) . "\n";
                        } else {
                            $yaml .= "\n" . $this->arrayToYaml($value, $indent + 1);
                        }
                    }
                } else {
                    $yaml .= $prefix . "- " . $this->formatValue($value) . "\n";
                }
            } else {
                // Key-value pair
                if (is_array($value)) {
                    if (empty($value)) {
                        $yaml .= $prefix . $key . ": []\n";
                    } else if ($this->isSequentialArray($value) && !$this->hasComplexValues($value)) {
                        // Simple list - inline format
                        $yaml .= $prefix . $key . ": [" . implode(', ', array_map([$this, 'formatValue'], $value)) . "]\n";
                    } else {
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
     * Check if array is associative
     */
    private function isAssoc(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
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
     * Check if array has complex values
     */
    private function hasComplexValues(array $arr): bool
    {
        foreach ($arr as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        return false;
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
            // Check if it's a secret reference
            if (strpos($value, '!secret') === 0) {
                return $value;
            }
            // Quote strings if they contain special characters
            if (preg_match('/[:\{\}\[\],&\*#\?|\-<>=!%@\\\\]/', $value) || 
                $value === '' || 
                trim($value) !== $value ||
                is_numeric($value)) {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        }
        return (string)$value;
    }
}

// Only run if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    try {
        $builder = new ModbusBuildESPHome();
        $builder->build();
    } catch (Exception $e) {
        echo "ESPHome build failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}