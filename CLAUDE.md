# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This is a community-driven database for heat pump Modbus registers, aimed at providing a common reference for different heat pump manufacturers' register configurations.

## Project Structure

- `src/` - Contains heat pump register definitions organized by manufacturer
  - `samsung/` - Samsung heat pump registers (Gen 6 and 7)
- `builds/` - Contains integrations for various platforms (e.g., Home Assistant, ESPHome) will be auto built from code
- `README.md` - Main documentation file

## Development Focus

This repository is primarily for documentation and data storage of Modbus register information. When contributing:

1. Heat pump register data should be organized by manufacturer under `src/`
3. All contributions should include clear documentation about the heat pump model and version

## Important Safety Note

As stated in the README, sending Modbus commands to heat pumps carries inherent risks. Any code or documentation should include appropriate warnings about potential equipment damage.