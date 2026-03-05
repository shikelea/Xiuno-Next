@echo off
echo =========================================
echo Xiuno Next Performance Benchmark Script
echo =========================================
echo.
echo Please ensure your local server is running at http://127.0.0.1:8080/
echo Requiring Apache Benchmark (ab).
echo.

set TARGET=http://127.0.0.1:8080/

echo [1/3] Benchmarking Homepage...
ab -n 1000 -c 50 %TARGET% > tmp/bench_home.txt
echo Homepage benchmark saved to tmp/bench_home.txt

echo [2/3] Benchmarking Forum List (fid=1)...
ab -n 1000 -c 50 %TARGET%?forum-1.htm > tmp/bench_forum.txt
echo Forum List benchmark saved to tmp/bench_forum.txt

echo [3/3] Benchmarking Thread Detail (tid=1)...
ab -n 500 -c 50 %TARGET%?thread-1.htm > tmp/bench_thread.txt
echo Thread Detail benchmark saved to tmp/bench_thread.txt

echo.
echo Benchmark completed! Please check the tmp/bench_*.txt files.
pause
