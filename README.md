# Heat Pump Modbus Database

<div align="center">

**A comprehensive, open-source database of Modbus registers for heat pump integration**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

*Maintained by [Warm Energy Labs Ltd](https://warm.energy) - Used in production for heat pump monitoring and control*

</div>

## Overview

Every heat pump manufacturer implements different Modbus registers for their systems. This project provides a unified, community-driven database of register mappings to simplify heat pump integration across different platforms.

This database is actively used internally at **Warm Energy Labs Ltd** for our heat pump monitoring and control systems, and we're sharing it with the community to advance heat pump integration standards.

## Disclaimer

**USE AT YOUR OWN RISK**: Sending Modbus commands to your heat pump could potentially damage it. We provide no guarantee that these register mappings are correct or safe for your specific equipment. Always consult your heat pump documentation and test carefully

## Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/warm-energy/modbus-heat-pump-database.git
   cd modbus-heat-pump-database
   ```

2. **Build configurations**
   ```bash
   ./build.sh
   ```

3. **Find your configurations**
   - Home Assistant: `builds/<manufacturer>/homeassistant/`
   - ESPHome: `builds/<manufacturer>/esphome/`

## Features

- **Multi-platform support**: Generates configurations for Home Assistant and ESPHome
- **Standardized format**: Common interface for similar registers across manufacturers
- **JSON source files**: Easy to read, edit, and validate
- **Automated builds**: Single command generates all platform configurations
- **Validation tools**: Built-in JSON validator ensures data quality

## Supported Heat Pumps

| Manufacturer | Model Series | Testing Status | Notes |
|--------------|-------------|----------------|-------|
| Samsung | **Gen 6 and Gen 7** | Tested | 20 models supported - see below |
| Grant/Aerona | Aerona3 (Chofu) | Not Tested | Draft modbus mappings from reverse engineering, baudrate: 19200 |

### Samsung Supported Models

All Samsung models use the same modbus register configuration (baudrate: 9600, parity: Even).

| Series | Models | Capacity Range |
|--------|--------|----------------|
| **BXYD Series** | AE080BXYDEG/EU, AE120BXYDEG/EU, AE140BXYDEG/EU | 8-14kW |
| | AE080BXYDGG/EU, AE120BXYDGG/EU, AE140BXYDGG/EU | 8-14kW |
| **CXYD Series** | AE050CXYDEK/EU, AE080CXYDEK/EU, AE120CXYDEK/EU, AE160CXYDEK/EU | 5-16kW |
| | AE080CXYDGK/EU, AE120CXYDGK/EU, AE160CXYDGK/EU | 8-16kW |
| **CXYB Series** | AE050CXYBEK/EU, AE080CXYBEK/EU, AE120CXYBEK/EU, AE160CXYBEK/EU | 5-16kW |
| | AE080CXYBGK/EU, AE120CXYBGK/EU, AE160CXYBGK/EU | 8-16kW |

**Tested Model**: AE160CXYDEK/EU has been confirmed working with this configuration.

## Project Structure

```
modbus-heat-pump-database/
├── src/                      # Source JSON register definitions
│   ├── samsung/             # Samsung heat pump registers
│   └── aerona/              # Grant/Aerona heat pump registers
├── builds/                   # Generated configurations (git-ignored)
│   └── <manufacturer>/
│       ├── homeassistant/   # Home Assistant YAML files
│       └── esphome/         # ESPHome device configs
├── build.sh                  # Main build script
├── build.php                 # Unified PHP builder
├── validate-json.php         # JSON validation tool
├── COMMON_INTERFACE.md       # Common register mappings
└── CONTRIBUTING.md           # Contribution guidelines
```

## Development

### Validate JSON Files
```bash
php validate-json.php
```

### Build Specific Platforms
```bash
php build.php                 # Build all formats
php build-esphome.php         # Build only ESPHome configs
```

### Clean Build
```bash
./build.sh --clean            # Remove old builds and rebuild
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:
- Adding new heat pump models
- Reporting issues
- Testing procedures
- Code standards

### Key Points for Contributors
1. Use the JSON validator before submitting
2. Follow the common interface standards in [COMMON_INTERFACE.md](COMMON_INTERFACE.md)
3. Clearly mark testing status
4. Include safety warnings where appropriate

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## About Warm Energy Labs

[Warm Energy Labs Ltd](https://warm.energy) specializes in heat pump monitoring and control systems. We use this database internally for our production systems and maintain it as an open-source resource for the community.

## Acknowledgments

This project was made possible thanks to the work of the following repositories:
- [ZimKev/MIM-B19n_Modbus](https://github.com/ZimKev/MIM-B19n_Modbus) - Samsung heat pump Modbus documentation
- [aerona-chofu-ashp/modbus](https://github.com/aerona-chofu-ashp/modbus) - Grant Aerona/Chofu heat pump Modbus mappings

## Related Projects

- [Home Assistant Modbus Integration](https://www.home-assistant.io/integrations/modbus/)
- [ESPHome Modbus Component](https://esphome.io/components/modbus.html)

## Support

- **Issues**: [GitHub Issues](https://github.com/warm-energy/modbus-heat-pump-database/issues)
- **Discussions**: [GitHub Discussions](https://github.com/warm-energy/modbus-heat-pump-database/discussions)
- **Commercial Support**: Contact [Warm Energy Labs Ltd](https://warm.energy)

---

<div align="center">
Made with care by <a href="https://warm.energy">Warm Energy Labs Ltd</a> for the heat pump community
</div>

