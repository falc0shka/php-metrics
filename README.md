
# PhpMetrics

Пакет для сбора и анализа метрик проектов

## Список возможных методов и событий

Методы для работы с метриками

- processStart()
- routeStart()
- routeFinishSuccess()
- routeFinishFail()
- updateMetric('metric_name', $metricParams)

Данные события можно вызывать методом dispatchEvent('EVENT_NAME', $eventParams) - DEPRECATED

- **PROCESS_START** - обязательное событие начала работы скрипта
- **ROUTE_START** - обязательное событие вызова целевого метода
- **ROUTE_FINISH_SUCCESS** - обязательное событие нормального окончание работы скрипта
- **ROUTE_FINISH_FAIL** - обязательное событие неудачного окончания работы скрипта
- **UPDATE_METRIC** - добавление или обновление метрики

## Результирующие метрики

### Базовые метрики

- **requests_hit_count** - общее количество запросов
- **requests_start_count** - количество вызовов целевого метода
- **requests_finish_success_count** - количество нормальных окончаний работы скрипта
- **requests_finish_fail_count** - количество неудачных окончаний работы скрипта
- **system_cpu_usage** - процент загруки процессора
- **system_load_average** - среднее значение загруженности системы
- **system_memory_usage** - рамер используемой памяти
- **system_memory_max** - максимальный размер памяти
- **system_disk_free_space** - свободное место на диске
- **system_disk_total_space** - общее место на диске
- **logging_max_memory** - максимальный расход памяти при формирования лога
- **logging_execution_time** - время формирования лога
- **logging_tags_count** - общее количество тегов

### Метрики для нормальных окончаний работы скрипта с префиксом 'success_'

Постоянные метрики:

- **success_max_memory** - максимальный расход памяти
- **success_execution_time** - время исполнения скрипта

Кастомные метрики:

- **success_db_request** - количество запросов к БД
- **success_db_responses_value** - дополнительное значение для запросов к БД
- **success_db_responses_time** - общее время исполнения запросов БД
- **success_db_responses_time_max** - максимальное время исполнения среди запросов БД
- **success_validation_errors** - кол-во ошибок валидации 
- и т.д.

### Метрики для окончаний работы скрипта по эксепшену с префиксом 'success_'

Постоянные метрики:

- **fail_max_memory** - максимальный расход памяти
- **fail_execution_time** - время исполнения скрипта

Кастомные метрики:

- **fail_db_requests** - количество запросов к БД
- **fail_db_responses_value** - дополнительное значение для запросов к БД
- **fail_db_responses_time** - общее время исполнения запросов БД
- **fail_db_responses_time_max** - максимальное время исполнения среди запросов БД
- **fail_validation_errors** - кол-во ошибок валидации
- и т.д.

## Рассчет базовых метрик

- **requests_hit_count** = count(PROCESS_START)
- **requests_start_count** = count(ROUTE_START)
- **requests_finish_success_count** = count(ROUTE_FINISH_SUCCESS)
- **requests_finish_fail_count** = count(ROUTE_FINISH_FAIL)
- **max_memory** = max(memory_usage_per_each_event)
- **execution_time** = time(ROUTE_FINISH_SUCCESS(FAIL)) - time(PROCESS_START)

## Рассчет произвольных метрик

- **db_requests** = count(DB_REQUEST)
- **db_responses_time** = execution_time(DB_RESPONSE)
- **db_responses_time_max** = max(execution_time(DB_RESPONSE))
- **some_custom_metric** = count(SOME_CUSTOM_METRIC)
- **some_custom_metric_value** = sum(SOME_CUSTOM_METRIC value)
- **validation_errors** = count(VALIDATION_ERROR)

## Использование в коде

1. Установка пакета

    ```composer
    composer require falc0shka/php-metrics
    ```

2. Инициализация пакета

    ```php
    use Falc0shka\PhpMetrics\PhpMetrics;
    
    $phpMetrics = PhpMetrics::getInstance();
    ```

3. Настройка

    ```php
    $phpMetrics->setTag($module . '::' . $action)           // Установить tag для текущего запроса
        ->setProject('test_project')                        // Установить название проекта
        ->setLogMaxAge(30)                                  // Установить срок жизни лог файлов
        ->setLogPath(dirname(__FILE__) . '/log');           // Установить путь для сохранения файлов (для файловых логгеров)
    ```

4. Включение логирования

    ```php
    $phpMetrics->disableMetrics();
    ```

5. Отключение логирования

    ```php
    $phpMetrics->disableMetrics();
    ```

6. Последовательность вызова обязательных методов

    ```php
    try {
    
        // Обязательный вызов события начала скрипта
        $phpMetrics->processStart();
        // Обязательный вызов события начала роута
        $phpMetrics->routeStart();
        
        // ...some logic...
        $out = call_user_func([$controller, $action]);
        $oResponce->output($out);
        
        // Вызов события нормального окончания скрипта
        $phpMetrics->routeFinishSuccess();
        
    } catch (Exception $e){
    
        // Обязательный вызов события неудачного окончания скрипта
        $phpMetrics->routeFinishFail();
        
        // ...some exception logic...
        if(method_exists($e, 'outputError')){
            $e->outputError();
        } else {
            die($e->getMessage());
        };
       
    }
    ```
7. Вызов методов обновления и формирования метрик

    Чтобы получить итоговую метрику при логировании, необходимо так вызвать метод updateMetric.
    По умолчанию каждая метрика является количественной, то есть подсчитывается кол-во вызовов данного метода.

    ```php
    $phpMetrics->updateMetric('some_metric');
    ```
   
    Дополнительно в метод можно передать массив параметров, который имеет следующую структуру.
   
    ```php
    $phpMetrics->updateMetric('some_metric', [
      'metric' => 'some_metric', // название метрики
      'value' => 10, // произвольное значение
      'time_start' => 572111700, // timestamp начала запроса
      'execution_time' => 0.555, // либо можно указать точное время запроса
    ]);
    ```

    В этом примере будут автоматически созданы соответствующие метрики

   - success_some_metric = 1                // Количественная метрика
   - success_some_metric_value = 10         // Накопительная метрика для произвольных значений
   - success_some_metric_time = 0.555       // Время исполнения (например время запроса)
   - success_some_metric_time_max = 0.555   // Максимальное время исполнения среди всех таких запросов

8. Включение системных метрик

    Для включения процесса сбора системных метрик

    ```php
    $phpMetrics->enableSystemMetrics();
    ```

9. Включение общих метрик со всех проектов

    Для включения процесса сбора общих метрик со всех проектов

    ```php
    $phpMetrics->enableAllProjectsMetrics();
    ```
