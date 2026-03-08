// === 核心函数：提取图片颜色（改进版）===
function extractColorsFromImage(image, maxColors = 20) {
    return new Promise((resolve) => {
        // 创建canvas处理图片
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // 缩小图片以提高性能（最大100px）
        const maxSize = 100;
        let width = image.width;
        let height = image.height;

        if (width > height && width > maxSize) {
            height = (height / width) * maxSize;
            width = maxSize;
        } else if (height > maxSize) {
            width = (width / height) * maxSize;
            height = maxSize;
        }

        canvas.width = width;
        canvas.height = height;
        ctx.drawImage(image, 0, 0, width, height);

        // 获取像素数据
        const imageData = ctx.getImageData(0, 0, width, height);
        const data = imageData.data;
        const colorMap = {};

        // 更精细的量化（每8个值一组）
        for (let i = 0; i < data.length; i += 4) {
            const alpha = data[i + 3];
            if (alpha < 128) continue; // 跳过透明像素

            // 颜色量化
            const r = Math.floor(data[i] / 8) * 8;
            const g = Math.floor(data[i + 1] / 8) * 8;
            const b = Math.floor(data[i + 2] / 8) * 8;
            const key = `${r},${g},${b}`;

            colorMap[key] = (colorMap[key] || 0) + 1;
        }

        // 提取原始颜色（保持原算法）
        const rawColors = Object.entries(colorMap)
            .sort((a, b) => b[1] - a[1])
            .map(([rgb]) => {
                const [r, g, b] = rgb.split(',').map(Number);
                return aether_color_extract_rgbToHex(r, g, b);
            })
            .slice(0, 50); // 取前50个原始颜色

        resolve(rawColors);
    });
}

// 过滤相似颜色
function filterSimilarColors(colors, threshold) {
    const filtered = [];

    colors.forEach(color => {
        // 检查是否与已添加的颜色相似
        const isSimilar = filtered.some(existingColor => {
            return colorSimilarity(color, existingColor) < threshold;
        });

        if (!isSimilar) {
            filtered.push(color);
        }
    });

    return filtered;
}

// 计算两个颜色的相似度（CIE76色差公式）
function colorSimilarity(color1, color2) {
    const [r1, g1, b1] = aether_color_extract_hexToRgb(color1);
    const [r2, g2, b2] = aether_color_extract_hexToRgb(color2);

    // 转换为LAB颜色空间（简化版）
    const lab1 = aether_color_extract_rgbToLab(r1, g1, b1);
    const lab2 = aether_color_extract_rgbToLab(r2, g2, b2);

    // 计算欧氏距离
    const deltaE = Math.sqrt(
        Math.pow(lab1[0] - lab2[0], 2) +
        Math.pow(lab1[1] - lab2[1], 2) +
        Math.pow(lab1[2] - lab2[2], 2)
    );

    // 归一化到0-1范围
    return deltaE / 100;
}

// RGB转LAB（更准确的实现）
function aether_color_extract_rgbToLab(r, g, b) {
    // 将RGB值归一化到0-1范围
    r = r / 255;
    g = g / 255;
    b = b / 255;

    // 应用伽马校正
    r = r > 0.04045 ? Math.pow((r + 0.055) / 1.055, 2.4) : r / 12.92;
    g = g > 0.04045 ? Math.pow((g + 0.055) / 1.055, 2.4) : g / 12.92;
    b = b > 0.04045 ? Math.pow((b + 0.055) / 1.055, 2.4) : b / 12.92;

    // 转换到XYZ颜色空间
    let x = r * 0.4124 + g * 0.3576 + b * 0.1805;
    let y = r * 0.2126 + g * 0.7152 + b * 0.0722;
    let z = r * 0.0193 + g * 0.1192 + b * 0.9505;

    // 归一化到标准光源D65
    x = x / 0.95047;
    y = y / 1.00000;
    z = z / 1.08883;

    // 应用XYZ到LAB的转换
    x = x > 0.008856 ? Math.pow(x, 1/3) : (7.787 * x) + (16/116);
    y = y > 0.008856 ? Math.pow(y, 1/3) : (7.787 * y) + (16/116);
    z = z > 0.008856 ? Math.pow(z, 1/3) : (7.787 * z) + (16/116);

    // 计算LAB值
    const l = (116 * y) - 16;
    const a = 500 * (x - y);
    const bVal = 200 * (y - z);

    return [l, a, bVal];
}

// === 辅助函数 ===
function aether_color_extract_rgbToHex(r, g, b) {
    return '#' + [r, g, b]
        .map(x => x.toString(16).padStart(2, '0'))
        .join('')
        .toUpperCase();
}

function aether_color_extract_hexToRgb(hex) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return [r, g, b];
}

// === 颜色聚类功能 ===
// 预设颜色名称表（扩展版，包含更多颜色）
var colorNames = [
    ["#000000", "Black"],
    ["#FFFFFF", "White"],
    ["#C0C0C0", "Silver"],
    ["#808080", "Gray"],
    ["#ef4444", "Red"],
    ["#dc2626", "Dark Red"],
    ["#b91c1c", "Darker Red"],
    ["#f97316", "Orange"],
    ["#ea580c", "Dark Orange"],
    ["#c2410c", "Darker Orange"],
    ["#f59e0b", "Amber"],
    ["#d97706", "Dark Amber"],
    ["#b45309", "Darker Amber"],
    ["#eab308", "Yellow"],
    ["#ca8a04", "Dark Yellow"],
    ["#a16207", "Darker Yellow"],
    ["#84cc16", "Lime"],
    ["#65a30d", "Dark Lime"],
    ["#4d7c0f", "Darker Lime"],
    ["#22c55e", "Green"],
    ["#16a34a", "Dark Green"],
    ["#15803d", "Darker Green"],
    ["#10b981", "Emerald"],
    ["#059669", "Dark Emerald"],
    ["#047857", "Darker Emerald"],
    ["#14b8a6", "Teal"],
    ["#0d9488", "Dark Teal"],
    ["#0f766e", "Darker Teal"],
    ["#06b6d4", "Cyan"],
    ["#0891b2", "Dark Cyan"],
    ["#0e7490", "Darker Cyan"],
    ["#0ea5e9", "Sky"],
    ["#0284c7", "Dark Sky"],
    ["#0369a1", "Darker Sky"],
    ["#3b82f6", "Blue"],
    ["#2563eb", "Dark Blue"],
    ["#1d4ed8", "Darker Blue"],
    ["#6366f1", "Indigo"],
    ["#4f46e5", "Dark Indigo"],
    ["#4338ca", "Darker Indigo"],
    ["#8b5cf6", "Violet"],
    ["#7c3aed", "Dark Violet"],
    ["#6d28d9", "Darker Violet"],
    ["#a855f7", "Purple"],
    ["#9333ea", "Dark Purple"],
    ["#7e22ce", "Darker Purple"],
    ["#d946ef", "Fuchsia"],
    ["#c026d3", "Dark Fuchsia"],
    ["#a21caf", "Darker Fuchsia"],
    ["#ec4899", "Pink"],
    ["#db2777", "Dark Pink"],
    ["#be185d", "Darker Pink"],
    ["#f43f5e", "Rose"],
    ["#e11d48", "Dark Rose"],
    ["#be123c", "Darker Rose"],
    ["#fbbf24", "Light Yellow"],
    ["#fcd34d", "Lighter Yellow"],
    ["#bef264", "Light Green"],
    ["#d9f99d", "Lighter Green"],
    ["#67e8f9", "Light Cyan"],
    ["#a5f3fc", "Lighter Cyan"],
    ["#93c5fd", "Light Blue"],
    ["#bfdbfe", "Lighter Blue"],
    ["#c4b5fd", "Light Violet"],
    ["#ddd6fe", "Lighter Violet"],
    ["#f9a8d4", "Light Pink"],
    ["#fce7f3", "Lighter Pink"]
];

// 计算两个颜色的欧几里得距离（LAB空间，更符合人眼感知）
function colorDistance(hex1, hex2) {
    const [r1, g1, b1] = aether_color_extract_hexToRgb(hex1);
    const [r2, g2, b2] = aether_color_extract_hexToRgb(hex2);

    // 转换为LAB颜色空间
    const lab1 = aether_color_extract_rgbToLab(r1, g1, b1);
    const lab2 = aether_color_extract_rgbToLab(r2, g2, b2);

    // 计算LAB空间中的欧几里得距离
    return Math.sqrt(
        Math.pow(lab1[0] - lab2[0], 2) +
        Math.pow(lab1[1] - lab2[1], 2) +
        Math.pow(lab1[2] - lab2[2], 2)
    );
}

// 找到最接近的标准颜色
function findClosestStandardColor(hex) {
    let minDistance = Infinity;
    let closestColor = "#000000";
    let closestName = "Black";

    for (const [standardHex, name] of colorNames) {
        const distance = colorDistance(hex, standardHex);
        if (distance < minDistance) {
            minDistance = distance;
            closestColor = standardHex;
            closestName = name;
        }
    }

    return { color: closestColor, name: closestName };
}

// 聚类函数：将相似颜色归到标准颜色
function clusterColorsToStandard(colors) {
    const clusters = {};

    // 初始化聚类
    colorNames.forEach(([hex, name]) => {
        clusters[hex] = {
            name: name,
            colors: [],
            count: 0
        };
    });

    // 将每个颜色分配到最近的聚类
    colors.forEach(hex => {
        const { color: closestColor } = findClosestStandardColor(hex);
        clusters[closestColor].colors.push(hex);
        clusters[closestColor].count++;
    });

    // 只保留有颜色的聚类，并排序
    const result = Object.entries(clusters)
        .filter(([_, cluster]) => cluster.count > 0)
        .sort((a, b) => b[1].count - a[1].count)
        .map(([standardHex, cluster]) => ({
            standard: standardHex,
            name: cluster.name,
            colors: cluster.colors,
            count: cluster.count
        }));

    return result;
}

// === 选择代表性颜色函数 ===
function selectRepresentativeColorsEnhanced(clusters, targetCount = 6) {
    // 1. 排除 white, gray, silver, black 的颜色
    const excludedNames = ['White', 'Gray', 'Silver', 'Black'];
    let filteredClusters = clusters.filter(cluster => {
        //console.log(excludedNames,cluster.name);
        return !excludedNames.includes(cluster.name);
    });

    // 如果过滤后没有颜色，返回空数组
    if (filteredClusters.length === 0) {
        return [];
    }

    // 2. 按 count 排序
    filteredClusters = filteredClusters.sort((a, b) => b.count - a.count);

    const selectedColors = [];
    const clusterCount = filteredClusters.length;

    // 3. 根据聚类数量选择不同数量的颜色
    if (clusterCount >= 6) {
        // 大于六个：从每个聚类中挑选一个，凑成五个
        for (let i = 0; i < 5 && i < filteredClusters.length; i++) {
            const cluster = filteredClusters[i];
            // 从聚类的实际颜色中选择第一个
            if (cluster.colors.length > 0) {
                selectedColors.push(cluster.colors[0]);
            }
        }
    } else if (clusterCount === 5) {
        // 五个：第一项拿两个，第二项拿一个，第三项拿一个，第四项拿一个，第五项拿一个
        if (filteredClusters[0] && filteredClusters[0].colors.length > 0) selectedColors.push(filteredClusters[0].colors[0]);
        if (filteredClusters[0] && filteredClusters[0].colors.length > 1) selectedColors.push(filteredClusters[0].colors[1]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 0) selectedColors.push(filteredClusters[1].colors[0]);
        if (filteredClusters[2] && filteredClusters[2].colors.length > 0) selectedColors.push(filteredClusters[2].colors[0]);
        if (filteredClusters[3] && filteredClusters[3].colors.length > 0) selectedColors.push(filteredClusters[3].colors[0]);
        if (filteredClusters[4] && filteredClusters[4].colors.length > 0) selectedColors.push(filteredClusters[4].colors[0]);
    } else if (clusterCount === 4) {
        // 四个：第一项拿两个，第二项拿两个，第三项拿一个，第四项拿一个
        if (filteredClusters[0] && filteredClusters[0].colors.length > 0) selectedColors.push(filteredClusters[0].colors[0]);
        if (filteredClusters[0] && filteredClusters[0].colors.length > 1) selectedColors.push(filteredClusters[0].colors[1]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 0) selectedColors.push(filteredClusters[1].colors[0]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 1) selectedColors.push(filteredClusters[1].colors[1]);
        if (filteredClusters[2] && filteredClusters[2].colors.length > 0) selectedColors.push(filteredClusters[2].colors[0]);
        if (filteredClusters[3] && filteredClusters[3].colors.length > 0) selectedColors.push(filteredClusters[3].colors[0]);
    } else if (clusterCount === 3) {
        // 三个：第一项拿两个，第二项拿两个，第三项拿两个
        if (filteredClusters[0] && filteredClusters[0].colors.length > 0) selectedColors.push(filteredClusters[0].colors[0]);
        if (filteredClusters[0] && filteredClusters[0].colors.length > 1) selectedColors.push(filteredClusters[0].colors[1]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 0) selectedColors.push(filteredClusters[1].colors[0]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 1) selectedColors.push(filteredClusters[1].colors[1]);
        if (filteredClusters[2] && filteredClusters[2].colors.length > 0) selectedColors.push(filteredClusters[2].colors[0]);
        if (filteredClusters[2] && filteredClusters[2].colors.length > 1) selectedColors.push(filteredClusters[2].colors[1]);
    } else if (clusterCount === 2) {
        // 二个：第一项拿三个，第二项拿三个
        if (filteredClusters[0] && filteredClusters[0].colors.length > 0) selectedColors.push(filteredClusters[0].colors[0]);
        if (filteredClusters[0] && filteredClusters[0].colors.length > 1) selectedColors.push(filteredClusters[0].colors[1]);
        if (filteredClusters[0] && filteredClusters[0].colors.length > 2) selectedColors.push(filteredClusters[0].colors[2]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 0) selectedColors.push(filteredClusters[1].colors[0]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 1) selectedColors.push(filteredClusters[1].colors[1]);
        if (filteredClusters[1] && filteredClusters[1].colors.length > 2) selectedColors.push(filteredClusters[1].colors[2]);
    } else if (clusterCount === 1) {
        // 一个：第一项拿六个
        const cluster = filteredClusters[0];
        for (let i = 0; i < 6 && i < cluster.colors.length; i++) {
            selectedColors.push(cluster.colors[i]);
        }
    }

    // 确保返回的颜色数量不超过目标数量
    return selectedColors.slice(0, targetCount);
}
// 完整流程
/**
 * # 【使用我】将图片里的颜色提取出来
 * @param {File} image - 要提取颜色的图片文件
 * @returns {Promise<Array>} - 提取到的颜色数组
 */
async function processImageToPalette(image) {
    // 1. 提取颜色
    const rawColors = await extractColorsFromImage(image, 50);

    // 2. 聚类到标准颜色
    const clusters = clusterColorsToStandard(rawColors);

    // 3. 选择合适的代表性颜色
    const selectedColors = selectRepresentativeColorsEnhanced(clusters, 6);

    console.log("聚类结果:", clusters);
    console.log("选择的颜色:", selectedColors);

    return selectedColors;
}