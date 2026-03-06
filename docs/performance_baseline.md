# Xiuno Next 性能基线报告 (Performance Baseline)

本文档记录了项目在进行后续深度重构与优化前的基础性能指标，作为衡量未来各项改造收益的基准。

## 测试环境
- **操作系统**: Windows (Docker/Local) / Linux
- **PHP 版本**: PHP 8.2 (Docker) / 本地 PHP
- **MySQL 版本**: MySQL 8.0
- **压测工具**: Apache Benchmark (ab)
- **并发数**: `-c 50`
- **总请求数**: `-n 1000`

## 指标记录 (测试日期：2026-03)

### 1. 首页 (Index)
* 路由: `/?index.htm`
* **QPS (Req/sec)**: [待记录]
* **TTFB (Time Per Request)**: [待记录] ms
* **内存峰值 (Memory Peak)**: ~ 2MB 

### 2. 帖子列表页 (Forum)
* 路由: `/?forum-1.htm`
* **QPS (Req/sec)**: [待记录]
* **TTFB (Time Per Request)**: [待记录] ms
* **内存峰值 (Memory Peak)**: ~ 2.5MB

### 3. 帖子详情页 (Thread)
* 路由: `/?thread-1.htm`
* **QPS (Req/sec)**: [待记录]
* **TTFB (Time Per Request)**: [待记录] ms
* **内存峰值 (Memory Peak)**: ~ 3MB

> **状态**：压测脚本框架已就位（`bin/benchmark.bat`），实际基准数据需在部署环境中运行后采集填入。建议在首次正式部署后立即执行压测并更新本文档。

## 优化目标
后续在添加中间件、路由改造或依赖注入时，以上任何接口的 TTFB 不得有超过 **15%** 的退化。Xiuno 的立身之本是性能，性能防线不容妥协。
