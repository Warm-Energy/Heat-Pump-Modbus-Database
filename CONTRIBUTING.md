# Contributing to Heat Pump Modbus Database

Thank you for your interest in contributing to the Heat Pump Modbus Database! This project is maintained by [Warm Energy Labs Ltd](https://warm.energy) and is used internally for heat pump integrations. We welcome community contributions to expand support for more heat pump models.

## How to Contribute

### Reporting Issues
- Check if the issue already exists
- Include heat pump model and version
- Provide error messages and logs
- Describe expected vs actual behavior

### Adding New Heat Pump Support

1. **Fork the repository**
2. **Create a new branch**: `git checkout -b add-<manufacturer>-support`
3. **Create manufacturer directory**: `src/<manufacturer>/`
4. **Add JSON configuration**: Create `modbus_registers.json` following the schema

#### JSON Structure Requirements

Your JSON file must include:
```json
{
  "make": "manufacturer_name",
  "modbus": {
    "models": ["Model1", "Model2"],
    "connection": {
      "baudrate": 9600,
      "bytesize": 8,
      "parity": "N",
      "stopbits": 1,
      "method": "rtu"
    },
    "registers": {
      "sensors": [...],
      "switches": [...],
      "climates": [...]
    }
  }
}
```

#### Essential Registers (Priority 1)
Please include these common registers if available:
- `outdoor_temperature` - Outside air temperature
- `water_inlet_temp` - Return water temperature  
- `water_outlet_temp` - Flow water temperature
- `operating_mode` - Current operation mode
- `error_code` - Error/fault codes

See [COMMON_INTERFACE.md](COMMON_INTERFACE.md) for complete common register mappings.

### Testing Your Contribution

1. **Validate JSON**: Run the validator
   ```bash
   php validate-json.php
   ```

2. **Build configurations**: Test the build process
   ```bash
   ./build.sh
   ```

3. **Mark testing status**: 
   - Tested - If you've tested with actual hardware
   - Not Tested - If based on documentation only

### Documentation

- Update README.md with your heat pump model in the supported table
- Include testing status
- Add any special notes or requirements
- Document any unlock registers or special sequences

### Pull Request Process

1. **Ensure JSON validates** without errors
2. **Build outputs generate** successfully
3. **Update documentation** as needed
4. **Create PR** with:
   - Heat pump manufacturer and models
   - Source of register information
   - Testing status
   - Any special considerations

### Code of Conduct

- Be respectful and constructive
- Provide accurate information
- Test when possible
- Document uncertainties

### Important Notes

- **Safety**: Always include warnings about potential equipment damage
- **Accuracy**: Only contribute register information you're confident about
- **Testing**: Clearly indicate if registers are tested or theoretical
- **Licensing**: All contributions are under MIT license

## Project Maintenance

This project is maintained by **Warm Energy Labs Ltd** and is actively used in production for heat pump monitoring and control systems. While we use it internally, we believe in open source collaboration to improve heat pump integration across the industry.

## Questions?

- Open an issue for questions
- Tag maintainers for urgent matters
- Check existing documentation first

Thank you for helping make heat pump integration more accessible!