# CHANGELOG

## 2.1.0 - 2026-01-21

### Features

* Add request finish checking

---

## 2.0.0 - 2026-01-19

### Features

* Add new methods for base event invoking

Breaking backward compatibility:

* Change metric creation and update mechanism through updateMetric() method

Metrics must be explicitly created with updateMetric() method. This method supports params array for value and timings.

---

## 1.9.0 - 2026-01-13

### Features

* Add log path checking
* Add validation errors metric
* Add disk free and total space system metrics

---

## 1.8.0 - 2025-11-25

### Features

* Add combined metrics from all projects in separate folder

---

## 1.7.0 - 2025-11-08

### Features

* Add separating log files by projects
* Add log files max age setting

---

## 1.6.0 - 2025-10-06

### Features

* Add db_requests_time_max metric
* Add api_requests_time_max metric

---

## 1.5.4 - 2025-09-29

### Features

* Add system metrics enable switch

---

## 1.5.3 - 2025-09-25

### Features

* Add system metrics

---

## 1.5.2 - 2025-09-20

### Fixes

* Fix some errors

---

## 1.5.1 - 2025-09-17

### Features

* Add logging base metrics
* Add composer install command

---

## 1.5.0 - 2025-09-02

### Features

* Add getLogs() processing blocking

---

## 1.4.0 - 2025-01-30

### Features

* Update getLogs() feature with archives list
* Add CUSTOM_METRIC event
* Change custom metrics calculation

### Fixes

* Disable zero metrics logging
* Change ALL to UNKNOWN tag

---

## 1.3.0 - 2024-09-04

### Features

* Add getLogs feature

### Fixes

* Fix interface signatures

---

## 1.2.0 - 2024-09-03

### Features

* Differentiate standard and optional metrics
* Change event names

---

## 1.1.0 - 2024-08-21

### Features

* Get metrics feature
* Differentiate success and exception metrics

### Fixes

* Translate changelog
* Fix base metric counting

---

## 1.0.0 - 2024-08-20

Initial release.

### Features

* APCU logger
* B2B collector
* Settings feature
* Enable/disable logging feature
* Metrics update feature


