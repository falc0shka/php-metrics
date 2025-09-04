
# PhpMetrics

Пакет для сбора и анализа метрик проектов

## Метрики

## Список возможных событий

- **PROCESS_START** - начало работы скрипта
- **ROUTE_START** - вызов целевого метода
- **ROUTE_FINISH_SUCCESS** - нормальное окончание работы скрипта
- **ROUTE_FINISH_EXCEPTION** - окончаний работы скрипта по эксепшену
- **DB_REQUEST** - запрос БД
- **DB_RESPONSE** - ответ БД
- **API_REQUEST** - запрос внешних API
- **API_RESPONSE** - ответ внешних API
- **CUSTOM_METRIC** - событие для подсчета кастомной метрики

### Базовые метрики

- **requests_hit_count** - общее количество запросов
- **requests_start_count** - количество вызовов целевого метода
- **requests_finish_success_count** - количество нормальных окончаний работы скрипта
- **requests_finish_exception_count** - количество окончаний работы скрипта по эксепшену

### Метрики для нормальных окончаний работы скрипта

- **success_max_memory** - максимальный расход памяти
- **success_execution_time** - время исполнения скрипта
- **success_db_requests** - количество запросов к БД
- **success_db_requests_time** - количество запросов к внешним API
- **success_api_requests** - время исполнения запросов БД
- **success_api_requests_time** - время ожидания внешних API 

### Метрики для окончаний работы скрипта по эксепшену

- **exception_max_memory** - максимальный расход памяти
- **exception_execution_time** - время исполнения скрипта
- **exception_db_requests** - количество запросов к БД
- **exception_db_requests_time** - количество запросов к внешним API
- **exception_api_requests** - время исполнения запросов БД
- **exception_api_requests_time** - время ожидания внешних API

### Кастомные метрики

- **test_custom_metric** - тестовая custom метрика

## Рассчет метрик

- **requests_hit_count** = count(PROCESS_START)
- **requests_start_count** = count(ROUTE_START)
- **requests_finish_success_count** = count(ROUTE_FINISH_SUCCESS)
- **requests_finish_exception_count** = count(ROUTE_FINISH_EXCEPTION)
- **max_memory** = max(memory_usage_per_each_event)
- **execution_time** = time(ROUTE_FINISH_SUCCESS - time(PROCESS_START)
- **db_requests** = count(DB_REQUEST)
- **db_requests_time** = time(DB_RESPONSE) - time(DB_REQUEST)
- **api_requests** = count(API_REQUEST)
- **api_requests_time** = time(API_RESPONSE) - time(API_REQUEST)

## Использование в коде

1. Инициализация пакета

    ```php
    use Falc0shka\PhpMetrics\PhpMetrics;
    
    $phpMetrics = PhpMetrics::getInstance();
    ```

2. Настройка

    ```php
    $phpMetrics->setTag($module . '::' . $action)          // Установить tag для текущего запроса
                ->setLogPath(dirname(__FILE__) . '/log');   // Установить путь для сохранения файлов (для файловых логгеров)
    ```

4. Отключение

    ```php
    $phpMetrics->disableMetrics();
    ```

5. Вызов событий

    ```php
    try {
    
        // Вызов события начала скрипта
        $phpMetrics->dispatchEvent('ROUTE_START');
        
        // ...some logic...
        $out = call_user_func([$controller, $action]);
        $oResponce->output($out);
        
        // Вызов события нормального окончания скрипта
        $phpMetrics->dispatchEvent('ROUTE_FINISH_SUCCESS');
        
    } catch (Exception $e){
    
        // Вызов события окончания скрипта по эксепшену
        $phpMetrics->dispatchEvent('ROUTE_FINISH_EXCEPTION');
        
        // ...some exception logic...
        if(method_exists($e, 'outputError')){
            $e->outputError();
        } else {
            die($e->getMessage());
        };
       
    }
    ```

6. Кастомные метрики

Для подсчета кастомных необходимо вызвать событие CUSTOM_METRIC,
и допонительное передать массив с названием метрики, тегом и значением

   ```php
   $phpMetrics->dispatchEvent('CUSTOM_METRIC', [
      'metric' => 'test_custom_metric_count',
      'tag' => 'CUSTOM_METRIC_TAG',
      'value' => 1,
   ]);
   ```

## Дополнительные возможности

1. Получить текущие значения метрик (DEPRECATED)

    ```php
    $phpMetrics->getMetrics();
    ```
   
2. Возможно установить метрики самостоятельно, а не использовать события (DEPRECATED)

    ```php
    $phpMetrics->setMetrics($standardMetrics);
    ```
   
    Формат $standardMetrics

    ```php
    $standardMetrics = [
        'metric1' => value1,
        'metric2' => value2,
        'metric3' => value3,
    ];
    ```

   Пример:

   ```php
   // Calculate any metric
   $metricValue = calculateMetric();
   
   // Get current standardMetrics
   $standardMetrics = $phpMetrics->getMetrics();
   
   // Update metric value
   $standardMetrics['metricKey'] = $metricValue;
   
   // Set standardMetrics
   $phpMetrics->setMetrics($standardMetrics);
   ```
