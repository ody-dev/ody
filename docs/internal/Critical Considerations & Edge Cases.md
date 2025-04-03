# Critical Considerations & Edge Cases**  
*(For Internal Documentation)*  🛠️

## **1. Process & Worker Management**  
### **1.1 Static Memory Persistence**  
**Issue**:  
Static variables (`static::$var`) and global state persist across worker restarts (`max_request`), because Swoole reuses the PHP process. Only a **full process kill** resets them.  

**How to Reproduce**:  
```php
// Set in config:
$server->set([
    'max_request' => 1,       // Restart worker after each request
    'reload_async' => true,   // Force abrupt restarts
    'max_wait_time' => 1,     // Allow 1ms for graceful exit
]);
```  
**Expected Behavior**:  
- Without `reload_async`, statics persist.  
- With `reload_async`, statics *may* reset (not guaranteed).  

**Solution**:  
- **Avoid statics** for app state. Use:  
  ```php
  $server->app = new App(); // Worker-scoped
  ```  
- Explicitly clear in `workerStart`:  
  ```php
  static::$app = null;
  ```  

---

## **2. Stateful Services & Memory Leaks**  
### **2.1 Singleton Contamination**  
**Issue**:  
Singletons (e.g., DB connections) retain state between requests, leading to:  
- Stale transactions.  
- Leaked request data (e.g., authentication context).  

**How to Reproduce**:  
```php
$db = $container->make('db');
$db->beginTransaction(); // Not rolled back on request end!
```  

**Solution**:  
- **Clone services** per-request:  
  ```php
  $container->bind('db', fn() => clone $originalDb);
  ```  
- **Reset state** in middleware:  
  ```php
  $db->rollBack(); // Cleanup before response
  ```  

### **2.2 File Descriptor Leaks**  
**Issue**:  
Unclosed resources (files, sockets) exhaust system limits.  

**How to Reproduce**:  
```php
$server->on('request', function () {
    $file = fopen('/tmp/log', 'a'); // Never closed!
});
```  

**Solution**:  
- Use coroutine-safe APIs:  
  ```php
  $content = Co\System::readFile('/tmp/log');
  ```  
- Or explicitly close:  
  ```php
  defer { fclose($file); };
  ```  

---

## **3. Concurrency & Race Conditions**  
### **3.1 Shared Memory Corruption**  
**Issue**:  
`Swoole\Table` or `$server->table` requires manual locking for writes.  

**How to Reproduce**:  
```php
// Concurrent increments corrupt data:
$server->table->incr('counter', 'count');
```  

**Solution**:  
- Use atomic operations:  
  ```php
  $server->table->lock();
  $server->table->set('counter', $value + 1);
  $server->table->unlock();
  ```  

### **3.2 Coroutine-Unsafe Libraries**  
**Issue**:  
Blocking calls (e.g., `file_get_contents`, `sleep`) stall the entire worker.  

**How to Reproduce**:  
```php
$server->on('request', function () {
    file_get_contents('http://slow-api'); // Blocks worker!
});
```  

**Solution**:  
- Replace with coroutine clients:  
  ```php
  $client = new Swoole\Coroutine\Http\Client('slow-api');
  $client->get('/');
  ```  

---

## **4. HTTP/API-Specific Issues**  
### **4.1 Keep-Alive Connection Contamination**  
**Issue**:  
Clients reuse connections, but worker state resets, leading to:  
- Mixed response bodies.  
- Leaked headers.  

**How to Reproduce**:  
```bash
curl -v --keepalive-time 30 http://api
```  

**Solution**:  
- Disable keep-alive:  
  ```php
  $response->header('Connection', 'close');
  ```  

### **4.2 Streaming Response Corruption**  
**Issue**:  
Interleaved `write()` calls break chunked responses.  

**How to Reproduce**:  
```php
$response->write('Chunk1');
go(function () use ($response) {
    $response->write('Chunk2'); // Race condition!
});
```  

**Solution**:  
- **Serialize writes** using channels:  
  ```php
  $channel = new Swoole\Coroutine\Channel(1);
  $channel->push('Chunk1');
  $response->write($channel->pop());
  ```  

---

## **5. Debugging & Observability**  
### **5.1 Lost Stack Traces**  
**Issue**:  
Coroutine crashes don’t log full traces.  

**How to Reproduce**:  
```php
go(function () { nonexistent_function(); });
```  

**Solution**:  
- Enable debug tracing:  
  ```php
  $server->set([
      'log_level' => SWOOLE_LOG_DEBUG,
      'trace_flags' => SWOOLE_TRACE_ALL,
  ]);
  ```  

### **5.2 Shared Metrics Distortion**  
**Issue**:  
`memory_get_usage()` includes other concurrent requests.  

**How to Reproduce**:  
```php
$server->on('request', function () {
    echo memory_get_usage(); // Wrong under load!
});
```  

**Solution**:  
- Use coroutine-local metrics:  
  ```php
  $memory = Co::stats()['coroutine_memory_usage'];
  ```  

---

## **Chaos Testing Checklist**  
| Scenario | Command | Expected Outcome |
|----------|---------|------------------|
| Worker restarts | `kill -9 $(pgrep -f swoole)` | Statics reset if `reload_async=true` |  
| Memory leaks | `valgrind --leak-check=yes php server.php` | No unfreed buffers |  
| Race conditions | `wrk -t10 -c100 -d60s http://api` | Zero corrupted responses |  

```
# 1. Spam requests with keep-alive
wrk -t4 -c100 -d60s --timeout 30s http://api

# 2. Randomly kill workers
watch -n1 "kill -9 $(ps aux | grep 'swoole' | awk '{print $2}')"

# 3. Monitor with strace
strace -ff -p $(pidof php) -o swoole_trace
```

---

## **Key Takeaways**  
1. **Statics are evil** → Use worker-scoped containers.  
2. **Assume nothing is isolated** → Test with `max_request=1`.  
3. **Blocking calls kill performance** → Always use coroutine clients.  
4. **Log coroutine IDs** → Debug with `Co::getCid()`.  

**Example Logging**:  
```php
$this->logger->info("Request handled", [
    'coroutine' => Co::getCid(),
    'worker' => $server->worker_id,
]);
```  
