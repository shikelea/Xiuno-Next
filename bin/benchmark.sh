#!/bin/bash
echo "========================================="
echo "Xiuno Next Performance Benchmark Script"
echo "========================================="
echo ""

# 修改为你的站点地址（末尾必须带 /）
TARGET="${1:-http://127.0.0.1/}"

# 检查 ab 是否安装
if ! command -v ab &> /dev/null; then
    echo "[错误] 未找到 ab (Apache Benchmark)，请先安装："
    echo "  Ubuntu/Debian: sudo apt install apache2-utils"
    echo "  CentOS/RHEL:   sudo yum install httpd-tools"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(dirname "$SCRIPT_DIR")"
mkdir -p "$APP_DIR/tmp"

echo "目标地址: $TARGET"
echo ""

echo "[1/3] 压测首页 (1000请求, 50并发)..."
ab -n 1000 -c 50 "$TARGET" 2>&1 | tee "$APP_DIR/tmp/bench_home.txt"
echo ""

echo "[2/3] 压测帖子列表 (1000请求, 50并发)..."
ab -n 1000 -c 50 "${TARGET}?forum-1.htm" 2>&1 | tee "$APP_DIR/tmp/bench_forum.txt"
echo ""

echo "[3/3] 压测帖子详情 (500请求, 50并发)..."
ab -n 500 -c 50 "${TARGET}?thread-1.htm" 2>&1 | tee "$APP_DIR/tmp/bench_thread.txt"
echo ""

echo "========================================="
echo "压测完成！结果已保存到 tmp/bench_*.txt"
echo "========================================="

# 提取关键指标
echo ""
echo "=== 关键指标汇总 ==="
for f in "$APP_DIR/tmp/bench_home.txt" "$APP_DIR/tmp/bench_forum.txt" "$APP_DIR/tmp/bench_thread.txt"; do
    name=$(basename "$f" .txt | sed 's/bench_//')
    qps=$(grep "Requests per second" "$f" | awk '{print $4}')
    ttfb=$(grep "Time per request" "$f" | head -1 | awk '{print $4}')
    echo "[$name] QPS: ${qps:-N/A} req/s | TTFB: ${ttfb:-N/A} ms"
done
