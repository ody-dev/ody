# Changelog

All notable changes to this project will be documented in this file.

## [0.2.0] "circe" - 2025-04-06

This release contains several major updates to the framework to improve performance and stability under high load.

### Added

- Async CQRS implementation
- RabbitMQ module
- Refactored logging component to use Monolog
- Updated documentation

### Changed

- Modifications that enable the usage of SWOOLE_HOOK_ALL
- Reworked application bootstrap phase to initialize an app instance for each worker separately
    - Prevents services from registering twice
    - Removed redundant isRunningInConsole check
    - Application can still be bootstrapped outside of a console environment (e.g., when running as standard PHP
      process, not in Swoole)
- Refactored HTTP: Converted ControllerPool from static to non-static service
    - Removed static keywords from ControllerPool properties and methods
    - Added constructor to ControllerPool to inject Container, Logger, and initial configuration
    - Registered ControllerPool as a singleton service within the main application service provider
    - Updated ControllerResolver to receive ControllerPool via constructor injection
    - Updated Application class to remove static configuration calls
- Refactored Router: Converted Router to non-static and streamlined controller resolution
    - Removed static properties and methods from Router/RouteGroup
    - Injected dependencies into Router constructor
    - Router::match now returns handler identifiers instead of resolved instances
    - Controller instance resolution now solely handled by ControllerDispatcher/ControllerResolver
    - Updated Application, RouteServiceProvider, Facade, and Loaders accordingly
- Made initial item creation synchronous during borrow process
    - Modified getPoolItemWrapper to call increaseItems() synchronously when pool is empty and below capacity

### Fixed

- Critical bug where static::$app was null on worker crash/restart
- Race condition in multi-worker Swoole environments where borrow() could time out waiting for pop() on an empty channel
- Several general bug fixes
- Improved error handling
- General performance improvements