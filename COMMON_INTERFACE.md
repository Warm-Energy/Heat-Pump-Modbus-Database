# Heat Pump Common Modbus Interface

This document defines common registers found across different heat pump manufacturers. When adding new heat pump support, these are the typical registers to look for.

## Common Register Categories

### Core Temperature Sensors
These are found in virtually all heat pumps:

| Common Name | Description | Samsung Example | Aerona Example | Typical Unit | Notes |
|------------|-------------|-----------------|----------------|--------------|-------|
| **outdoor_temperature** | Outside air temperature | EHS_Outside_Temp | Outdoor_Air_Temperature | °C | Essential for weather compensation |
| **water_inlet_temp** | Return water temperature | EHS_Water_IN_Temp | Return_Water_Temperature | °C | Water returning to heat pump |
| **water_outlet_temp** | Flow/outgoing water temperature | EHS_Water_OUT_Temp | Outgoing_Water_Temperature | °C | Water leaving heat pump |
| **water_outlet_target** | Target flow temperature (read) | EHS_Water_OUT_Set_Temp | - | °C | Current setpoint for water temperature |
| **flow_temp_control** | Flow temp control register (write) | Register 68 (target_temp_register) | Register 2 (Heating_Zone1_Fixed_Water_Setpoint) | °C | **CRITICAL: Write register to control flow temperature** |
| **discharge_temp** | Compressor discharge temperature | EHS_Discharge_Temp | Discharge_Temperature | °C | Hot gas temperature |
| **suction_temp** | Compressor suction temperature | EHS_Suction_Temperature | Suction_Temperature | °C | Cold gas temperature |
| **defrost_temp** | Defrost/evaporator temperature | EHS_Defrost_Operation | Defrost_Temperature | °C | Used for defrost control |

### Zone/Room Control
Multi-zone heating control:

| Common Name | Description | Samsung Example | Aerona Example | Typical Unit |
|------------|-------------|-----------------|----------------|--------------|
| **zone1_room_temp** | Zone 1 room temperature | EHS_Water_Zone_1_temp | Zone1_Room_Temperature | °C |
| **zone2_room_temp** | Zone 2 room temperature | EHS_Water_Zone_2_temp | Zone2_Room_Temperature | °C |
| **zone1_setpoint** | Zone 1 target temperature | Climate target_temp_register | Heating_Zone1_Fixed_Water_Setpoint | °C |
| **zone2_setpoint** | Zone 2 target temperature | Climate target_temp_register | Heating_Zone2_Fixed_Water_Setpoint | °C |

### Domestic Hot Water (DHW)
Hot water tank control:

| Common Name | Description | Samsung Example | Aerona Example | Typical Unit |
|------------|-------------|-----------------|----------------|--------------|
| **dhw_tank_temp** | Current DHW tank temperature | EHS_Hot_Water_Set (addr 75) | DHW_Tank_Temperature | °C |
| **dhw_setpoint** | DHW target temperature | target_temp_register: 74 | DHW_Comfort_Temperature | °C |
| **dhw_mode** | DHW operating mode | EHS_Hot_Water_Mode | DHW_Operating_Mode | Enum |

### Operating Status
System state and modes:

| Common Name | Description | Samsung Example | Aerona Example | Typical Values |
|------------|-------------|-----------------|----------------|----------------|
| **operating_mode** | Current operating mode | EHS_Operation_Mode | Operating_Mode | 0=Off, 1=Heating, 2=Cooling |
| **compressor_status** | Compressor on/off state | EHS_Compressor_Status | Operating_Mode (derived) | 0=Off, 1=On |
| **error_code** | Current error/fault code | EHS_Error_Code | Current_Error_Code | Numeric code |
| **water_flow_rate** | Water flow through system | EHS_Water_Flow | - | l/min |

### Electrical & Pressure
System monitoring:

| Common Name | Description | Samsung Example | Aerona Example | Typical Unit |
|------------|-------------|-----------------|----------------|--------------|
| **compressor_current** | Compressor current draw | EHS_Compressor_Current | - | A |
| **compressor_frequency** | Inverter frequency | EHS_Compressor_Current_Frequency | - | Hz |
| **high_pressure** | High side pressure | EHS_High_Pressure | - | bar/kgfcm2 |
| **low_pressure** | Low side pressure | EHS_Low_Pressure | - | bar/kgfcm2 |

### Control Switches
Common control functions:

| Common Name | Description | Samsung Example | Aerona Example |
|------------|-------------|-----------------|----------------|
| **quiet_mode** | Quiet/silent operation | EHS_Quiet_Control | - |
| **away_mode** | Vacation/away function | EHS_Away_function | - |
| **anti_legionella** | Legionella prevention | - | Anti_Legionella_Function |
| **frost_protection** | Frost protection enable | - | Frost protection registers |

## Implementation Guidelines

When adding a new heat pump manufacturer:

1. **Priority 1 - Essential Registers** (Must have for basic operation):
   - outdoor_temperature
   - water_inlet_temp
   - water_outlet_temp
   - operating_mode
   - error_code

2. **Priority 2 - Important for Control**:
   - water_outlet_target
   - zone1_room_temp (if applicable)
   - dhw_tank_temp (if DHW supported)
   - compressor_status

3. **Priority 3 - Advanced Monitoring**:
   - discharge_temp
   - suction_temp
   - high_pressure
   - low_pressure
   - water_flow_rate
   - compressor_frequency

## JSON Schema Extension

To mark common registers in JSON files, add a `common_interface` field:

```json
{
  "name": "EHS_Outside_Temp",
  "address": 13,
  "unit": "°C",
  "common_interface": "outdoor_temperature"
}
```

This allows build tools to automatically map common functions across different manufacturers.