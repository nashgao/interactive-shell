# Config-Driven Mapping System

## Overview

The Space-Utils transformation system is now config-driven, allowing fine-grained control over which function mappings are applied through the `space-utils.json` configuration file.

## Configuration Structure

```json
{
  "space_utils": {
    "enabled": true,
    "mappings": {
      "registry": "../mappings/function-registry.json",
      "enabled_categories": {
        "array_operations": true,
        "string_operations": true,
        "file_operations": false  // Disable specific categories
      },
      "custom_registries": [
        ".claude/mappings/team-mappings.json",
        ".claude/mappings/project-mappings.json"
      ],
      "priority_overrides": {
        "team": 60,
        "custom": 70,
        "project": 80
      }
    }
  }
}
```

## Key Features

### 1. Registry Reference
- **`mappings.registry`**: Points to the main function-registry.json file
- No duplication - config references existing mappings
- Maintains single source of truth for function transformations

### 2. Category Control
- **`enabled_categories`**: Enable/disable specific transformation categories
- Categories include:
  - `array_operations` - Array manipulation functions
  - `string_operations` - String handling functions
  - `file_operations` - File system operations
  - `json_operations` - JSON encoding/decoding
  - `validation` - Input validation functions
  - `error_handling` - Error management patterns
  - And more...

### 3. Custom Registries
- **`custom_registries`**: Array of additional mapping files
- Allows team-specific or project-specific transformations
- Files are loaded in priority order

### 4. Priority System
- **`priority_overrides`**: Control which mappings take precedence
- Higher priority values override lower ones
- Default priorities:
  - Core registry: 50
  - Team mappings: 60
  - Custom mappings: 70
  - Project mappings: 80

## How It Works

1. **Config Loading**: Transformer reads `space-utils.json` first
2. **Registry Resolution**: Locates mapping files from config paths
3. **Category Filtering**: Only loads enabled categories
4. **Priority Merging**: Higher priority mappings override lower ones
5. **Transformation**: Applies selected mappings to PHP code

## Usage Examples

### Disable Specific Categories
```json
{
  "mappings": {
    "enabled_categories": {
      "file_operations": false,  // Don't transform file functions
      "async_operations": false   // Keep native async patterns
    }
  }
}
```

### Add Project-Specific Mappings
```json
{
  "mappings": {
    "custom_registries": [
      ".claude/mappings/legacy-compat.json",
      ".claude/mappings/domain-specific.json"
    ]
  }
}
```

### Override Priorities
```json
{
  "mappings": {
    "priority_overrides": {
      "legacy-compat": 90  // Highest priority for legacy code
    }
  }
}
```

## Hook Integration

The hooks automatically use the config when transforming code:

1. **Pre-edit hooks** check for config existence
2. **Transformer** loads config and applies settings
3. **Post-edit hooks** validate transformed code

## Benefits

- ✅ **Centralized Control**: Single config file controls all transformations
- ✅ **Backward Compatible**: Works with existing hook infrastructure
- ✅ **Flexible**: Enable/disable transformations per category
- ✅ **Extensible**: Easy to add new mapping files and categories
- ✅ **No Duplication**: References existing mappings, doesn't duplicate them

## Migration from Legacy Setup

If you have existing hooks without config:
1. The system will use default behavior (all categories enabled)
2. Add `space-utils.json` to gain config control
3. Existing custom mapping files continue to work

## Troubleshooting

- **Transformations not applying**: Check category is enabled in config
- **Wrong transformation used**: Verify priority settings
- **Custom mappings ignored**: Ensure path in `custom_registries` is correct
- **Hook failures**: Check both config and registry files exist