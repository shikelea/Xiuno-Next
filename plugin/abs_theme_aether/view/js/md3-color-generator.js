/**
 * MD3颜色生成器
 * 基于PHP实现转换为JavaScript
 */

/**
 * HEX转换为RGB
 * @param {string} hex 十六进制颜色值
 * @return {Object} RGB值
 */
function hexToRgb(hex) {
    if (hex.startsWith('#')) {
        hex = hex.substring(1);
    }

    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }

    return {
        r: parseInt(hex.substring(0, 2), 16),
        g: parseInt(hex.substring(2, 4), 16),
        b: parseInt(hex.substring(4, 6), 16)
    };
}

/**
 * RGB转换为HEX
 * @param {number} r 红色通道值(0-255)
 * @param {number} g 绿色通道值(0-255)
 * @param {number} b 蓝色通道值(0-255)
 * @return {string} 十六进制颜色值
 */
function rgbToHex(r, g, b) {
    return '#' + 
        Math.round(r).toString(16).padStart(2, '0') +
        Math.round(g).toString(16).padStart(2, '0') +
        Math.round(b).toString(16).padStart(2, '0');
}

/**
 * 颜色转换为HSL
 * @param {string} hex 颜色的十六进制代码（例如 #abcdef）
 * @param {boolean} return_array 是否返回数组，默认返回字符串
 * @return {string|Object} HSL值
 */
function hexToHsl(hex, return_array = false) {
    if (hex.startsWith('#')) {
        hex = hex.substring(1);
    }

    const red = parseInt(hex.substring(0, 2), 16) / 255;
    const green = parseInt(hex.substring(2, 4), 16) / 255;
    const blue = parseInt(hex.substring(4, 6), 16) / 255;

    const cmin = Math.min(red, green, blue);
    const cmax = Math.max(red, green, blue);
    const delta = cmax - cmin;

    let hue = 0;
    if (delta !== 0) {
        if (cmax === red) {
            hue = ((green - blue) / delta);
        } else if (cmax === green) {
            hue = ((blue - red) / delta + 2);
        } else {
            hue = ((red - green) / delta + 4);
        }
    }

    hue = Math.round(hue * 60);
    if (hue < 0) {
        hue += 360;
    }

    let lightness = ((cmax + cmin) / 2);
    let saturation = delta === 0 ? 0 : (delta / (1 - Math.abs(2 * lightness - 1)));
    if (saturation < 0) {
        saturation += 1;
    }

    lightness = Math.round(lightness * 100);
    saturation = Math.round(saturation * 100);
    
    if (return_array) {
        return {
            h: hue,
            s: saturation,
            l: lightness
        };
    } else {
        return hue + ', ' + saturation + '%, ' + lightness + '%';
    }
}

/**
 * HSL转换为HEX
 * @param {number} h 色相(0-360)
 * @param {number} s 饱和度(0-100)
 * @param {number} l 亮度(0-100)
 * @return {string} 十六进制颜色值
 */
function hslToHex(h, s, l) {
    h = h / 360;
    s = s / 100;
    l = l / 100;

    let r, g, b;

    if (s === 0) {
        r = g = b = l;
    } else {
        const hue2rgb = function(p, q, t) {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
            return p;
        };

        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;

        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
    }

    return rgbToHex(r * 255, g * 255, b * 255);
}

/**
 * 将饱和度从0-100范围重新映射到min-max范围
 * @param {number} s 原始饱和度
 * @param {number} min 最小饱和度
 * @param {number} max 最大饱和度
 * @return {number} 映射后的饱和度
 */
function remapSaturation(s, min = 0, max = 100) {
    return Math.round(min + (s / 100) * (max - min));
}

/**
 * 基于色相调整饱和度
 * 黄色部分饱和度降低，蓝色部分饱和度提高
 * @param {number} hue 色相值(0-360)
 * @return {number} 调整后的饱和度
 */
function adjustSaturationByHue(hue) {
    hue = hue % 360;
    if (hue < 0) hue += 360;

    // 简化：关键点数组
    const points = {
        0: 65,   // 红
        60: 55,  // 黄
        120: 50, // 绿
        180: 65, // 青
        240: 100, // 蓝
        300: 70, // 紫
        360: 65  // 红
    };

    // 找到边界
    let prev = 0;
    let next = 360;
    for (const key in points) {
        if (parseInt(key) <= hue) prev = parseInt(key);
        if (parseInt(key) >= hue) {
            next = parseInt(key);
            break;
        }
    }

    // 插值
    if (prev === next) return points[prev];
    const ratio = (hue - prev) / (next - prev);
    return Math.round(points[prev] + (points[next] - points[prev]) * ratio);
}

/**
 * 生成Material Design 3色彩调色板
 * @param {string} hex 主色十六进制值
 * @param {boolean} darkMode 是否为夜间模式（默认为false）
 * @return {Object} MD3色彩变体数组
 */
function generateMd3ColorPalette(hex, darkMode = false) {
    // 首先获取主色的HSL值
    const hsl = hexToHsl(hex, true);

    // 基于色相调整饱和度
    const baseSaturation = hsl.s;
    const adjustedSaturation = adjustSaturationByHue(hsl.h);

    if (darkMode) {
        // 夜间模式：在调整后的饱和度基础上进行夜间模式映射
        hsl.s = remapSaturation(hsl.s, 10, Math.round(adjustedSaturation * 0.8));
    } else {
        // 日间模式：在调整后的饱和度基础上进行日间模式映射
        hsl.s = remapSaturation(hsl.s, 10, adjustedSaturation);
    }

    // MD3色调级别 (0-100，间隔10)
    const toneLevels = [0, 4, 5, 6, 10, 12, 15, 17, 20, 22, 24, 25, 30, 35, 40, 50, 60, 70, 80, 90, 92, 94, 95, 96, 98, 99, 100];
    const palette = {};

    // 夜间模式色调映射反转
    let toneMapping = {};
    if (darkMode) {
        // 夜间模式：将亮色调映射到暗色调，暗色调映射到亮色调
        const toneLevelsFlipped = [...toneLevels].reverse();
        toneLevels.forEach((level, index) => {
            toneMapping[level] = toneLevelsFlipped[index];
        });
    }

    // 为每个色调级别生成颜色
    for (const tone of toneLevels) {
        const targetTone = darkMode ? toneMapping[tone] : tone;
        // 保持色相和饱和度不变，只调整亮度
        palette[tone] = hslToHex(hsl.h, hsl.s, targetTone);
    }

    return palette;
}

/**
 * 生成Material Design 3 CSS变量
 * @param {string} primaryHex 主色十六进制值
 * @param {string} secondaryHex 辅助色十六进制值（可选）
 * @param {string} tertiaryHex 第三色十六进制值（可选）
 * @return {string} CSS变量代码
 */
function generateMd3CssVariables(primaryHex = '#1e91ff') {
    let secondaryHex = hexToHsl(primaryHex, true);
    // 如果h小于等于180，增加40，否则减少40
    secondaryHex.h = secondaryHex.h <= 180 ? secondaryHex.h + 40 : secondaryHex.h - 40;
    secondaryHex = hslToHex(secondaryHex.h, secondaryHex.s, secondaryHex.l);

    let tertiaryHex = hexToHsl(primaryHex, true);
    // 转半圈
    tertiaryHex.h = (tertiaryHex.h + 180) % 360;
    tertiaryHex = hslToHex(tertiaryHex.h, tertiaryHex.s, tertiaryHex.l);
    
    // 生成各种颜色的调色板
    const primaryPalette = generateMd3ColorPalette(primaryHex);
    const primaryPaletteDark = generateMd3ColorPalette(primaryHex, true);

    const secondaryPalette = generateMd3ColorPalette(secondaryHex);
    const secondaryPaletteDark = generateMd3ColorPalette(secondaryHex, true);
    
    const tertiaryPalette = generateMd3ColorPalette(tertiaryHex);
    const tertiaryPaletteDark = generateMd3ColorPalette(tertiaryHex, true);

    // 生成中性色
    let neutralHex = hexToHsl(primaryHex, true);
    neutralHex.s = Math.round(neutralHex.s * 0.5);
    neutralHex.l = 57;
    neutralHex = hslToHex(neutralHex.h, neutralHex.s, neutralHex.l);
    const neutralPalette = generateMd3ColorPalette(neutralHex);
    const neutralPaletteDark = generateMd3ColorPalette(neutralHex, true);

    let neutralVariantHex = hexToHsl(primaryHex, true);
    neutralVariantHex.s = Math.round(neutralVariantHex.s * 0.333);
    neutralVariantHex.l = 45;
    neutralVariantHex = hslToHex(neutralVariantHex.h, neutralVariantHex.s, neutralVariantHex.l);
    const neutralVariantPalette = generateMd3ColorPalette(neutralVariantHex);
    const neutralVariantPaletteDark = generateMd3ColorPalette(neutralVariantHex, true);

    const errorHex = '#ff6363';
    const errorPalette = generateMd3ColorPalette(errorHex);
    const errorPaletteDark = generateMd3ColorPalette(errorHex, true);

    // 生成CSS变量
    let css = '';

    // 亮色模式
    css += ':root, [data-bs-theme=light] {' + '\n';
    // 主色变量
    Object.entries(primaryPalette).forEach(([tone, color]) => {
        css += '    --md3-primary-' + tone + ': ' + color + ';' + '\n';
    });

    // 辅助色变量
    Object.entries(secondaryPalette).forEach(([tone, color]) => {
        css += '    --md3-secondary-' + tone + ': ' + color + ';' + '\n';
    });

    // 第三色变量
    Object.entries(tertiaryPalette).forEach(([tone, color]) => {
        css += '    --md3-tertiary-' + tone + ': ' + color + ';' + '\n';
    });

    // 中性色变量
    Object.entries(neutralPalette).forEach(([tone, color]) => {
        css += '    --md3-neutral-' + tone + ': ' + color + ';' + '\n';
    });

    // 中性变体变量
    Object.entries(neutralVariantPalette).forEach(([tone, color]) => {
        css += '    --md3-neutral-variant-' + tone + ': ' + color + ';' + '\n';
    });
    // 系统颜色映射
    css += '        /* 系统颜色映射 */' + '\n';
    css += '    --md3-primary: ' + primaryPalette[60] + ';' + '\n';
    css += '    --md3-primary-rgb: ' + Object.values(hexToRgb(primaryPalette[60])).join(', ') + ';' + '\n';

    css += '    --md3-on-primary: ' + primaryPalette[100] + ';' + '\n';
    css += '    --md3-primary-container: ' + primaryPalette[30] + ';' + '\n';
    css += '    --md3-on-primary-container: ' + primaryPalette[90] + ';' + '\n';
    css += '    ' + '\n';
    css += '    --md3-secondary: ' + secondaryPalette[60] + ';' + '\n';
    css += '    --md3-secondary-rgb: ' + Object.values(hexToRgb(secondaryPalette[60])).join(', ') + ';' + '\n';
    css += '    --md3-on-secondary: ' + secondaryPalette[100] + ';' + '\n';
    css += '    --md3-secondary-container: ' + secondaryPalette[30] + ';' + '\n';
    css += '    --md3-on-secondary-container: ' + secondaryPalette[90] + ';' + '\n';
    css += '    ' + '\n';
    css += '    --md3-tertiary: ' + tertiaryPalette[60] + ';' + '\n';
    css += '    --md3-tertiary-rgb: ' + Object.values(hexToRgb(tertiaryPalette[60])).join(', ') + ';' + '\n';
    css += '    --md3-on-tertiary: ' + tertiaryPalette[100] + ';' + '\n';
    css += '    --md3-tertiary-container: ' + tertiaryPalette[30] + ';' + '\n';
    css += '    --md3-on-tertiary-container: ' + tertiaryPalette[90] + ';' + '\n';
    css += '    ' + '\n';
    css += '    --md3-surface: ' + neutralPalette[98] + ';' + '\n';
    css += '    --md3-surface-variant: ' + neutralVariantPalette[30] + ';' + '\n';
    css += '    --md3-background: ' + neutralPalette[98] + ';' + '\n';
    css += '    --md3-background-rgb: ' + Object.values(hexToRgb(neutralPalette[98])).join(', ') + ';' + '\n';
    css += '    --md3-error:' + errorPalette[60] + ';' + '\n';
    css += '    --md3-on-error: ' + errorPalette[100] + ';' + '\n';
    css += '    --md3-error-container: ' + errorPalette[30] + ';' + '\n';
    css += '    --md3-on-error-container: ' + errorPalette[80] + ';' + '\n';
    css += '    --md3-outline: ' + neutralVariantPalette[80] + ';' + '\n';
    css += '    --md3-outline-variant: ' + neutralVariantPalette[95] + ';' + '\n';
    css += '    --md3-shadow: ' + neutralPalette[0] + ';' + '\n';
    css += '    --md3-scrim: ' + neutralPalette[0] + ';' + '\n';
    css += '    --md3-inverse-surface: ' + neutralPalette[35] + ';' + '\n';
    css += '    --md3-inverse-surface-rgb: ' + Object.values(hexToRgb(neutralPalette[35])).join(', ') + ';' + '\n';
    css += '    --md3-inverse-on-surface: ' + neutralPalette[95] + ';' + '\n';
    css += '    --md3-inverse-on-surface-rgb: ' + Object.values(hexToRgb(neutralPalette[95])).join(', ') + ';' + '\n';
    css += '    --md3-inverse-primary: ' + primaryPalette[40] + ';' + '\n';
    css += '    --md3-primary-fixed: ' + primaryPalette[30] + ';' + '\n';
    css += '    --md3-primary-fixed-dim: ' + primaryPalette[20] + ';' + '\n';
    css += '    --md3-on-primary-fixed: ' + primaryPalette[90] + ';' + '\n';
    css += '    --md3-on-primary-fixed-variant: ' + primaryPalette[80] + ';' + '\n';
    css += '    --md3-secondary-fixed: ' + secondaryPalette[30] + ';' + '\n';
    css += '    --md3-secondary-fixed-dim: ' + secondaryPalette[20] + ';' + '\n';
    css += '    --md3-on-secondary-fixed: ' + secondaryPalette[90] + ';' + '\n';
    css += '    --md3-on-secondary-fixed-variant: ' + secondaryPalette[80] + ';' + '\n';
    css += '    --md3-tertiary-fixed: ' + tertiaryPalette[30] + ';' + '\n';
    css += '    --md3-tertiary-fixed-dim: ' + tertiaryPalette[20] + ';' + '\n';
    css += '    --md3-on-tertiary-fixed: ' + tertiaryPalette[90] + ';' + '\n';
    css += '    --md3-on-tertiary-fixed-variant: ' + tertiaryPalette[80] + ';' + '\n';
    css += '    --md3-surface-dim: ' + neutralPalette[95] + ';' + '\n';
    css += '    --md3-surface-bright: ' + neutralPalette[98] + ';' + '\n';
    css += '    --md3-surface-container-lowest: ' + neutralPalette[100] + ';' + '\n';
    css += '    --md3-surface-container-low: ' + neutralPalette[96] + ';' + '\n';
    css += '    --md3-surface-container: ' + neutralPalette[94] + ';' + '\n';
    css += '    --md3-surface-container-high: ' + neutralPalette[92] + ';' + '\n';
    css += '    --md3-surface-container-highest: ' + neutralPalette[90] + ';' + '\n';

    css += '}' + '\n';

    // 暗色模式
    css += '[data-bs-theme=dark] {' + '\n';
    // 主色变量
    Object.entries(primaryPaletteDark).forEach(([tone, color]) => {
        css += '    --md3-primary-' + tone + ': ' + color + ';' + '\n';
    });

    // 辅助色变量
    Object.entries(secondaryPaletteDark).forEach(([tone, color]) => {
        css += '    --md3-secondary-' + tone + ': ' + color + ';' + '\n';
    });

    // 第三色变量
    Object.entries(tertiaryPaletteDark).forEach(([tone, color]) => {
        css += '    --md3-tertiary-' + tone + ': ' + color + ';' + '\n';
    });

    // 中性色变量
    Object.entries(neutralPaletteDark).forEach(([tone, color]) => {
        css += '    --md3-neutral-' + tone + ': ' + color + ';' + '\n';
    });

    // 中性变体变量
    Object.entries(neutralVariantPaletteDark).forEach(([tone, color]) => {
        css += '    --md3-neutral-variant-' + tone + ': ' + color + ';' + '\n';
    });
    // 系统颜色映射
    css += '        /* 系统颜色映射 */' + '\n';
    css += '    --md3-primary: ' + primaryPaletteDark[24] + ';' + '\n';
    css += '    --md3-primary-rgb: ' + Object.values(hexToRgb(primaryPaletteDark[24])).join(', ') + ';' + '\n';
    css += '    --md3-on-primary: ' + primaryPaletteDark[0] + ';' + '\n';
    css += '    --md3-primary-container: ' + primaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-on-primary-container: ' + primaryPaletteDark[10] + ';' + '\n';
    css += '    ' + '\n';
    css += '    --md3-secondary: ' + secondaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-secondary-rgb: ' + Object.values(hexToRgb(secondaryPaletteDark[30])).join(', ') + ';' + '\n';
    css += '    --md3-on-secondary: ' + secondaryPaletteDark[0] + ';' + '\n';
    css += '    --md3-secondary-container: ' + secondaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-on-secondary-container: ' + secondaryPaletteDark[10] + ';' + '\n';
    css += '    ' + '\n';
    css += '    --md3-tertiary: ' + tertiaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-tertiary-rgb: ' + Object.values(hexToRgb(tertiaryPaletteDark[30])).join(', ') + ';' + '\n';
    css += '    --md3-on-tertiary: ' + tertiaryPaletteDark[0] + ';' + '\n';
    css += '    --md3-tertiary-container: ' + tertiaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-on-tertiary-container: ' + tertiaryPaletteDark[10] + ';' + '\n';
    css += '    ' + '\n';
    css += '    --md3-surface: ' + neutralPaletteDark[98] + ';' + '\n';
    css += '    --md3-surface-variant: ' + neutralVariantPaletteDark[30] + ';' + '\n';
    css += '    --md3-background: ' + neutralPaletteDark[98] + ';' + '\n';
    css += '    --md3-background-rgb: ' + Object.values(hexToRgb(neutralPaletteDark[98])).join(', ') + ';' + '\n';
    css += '    --md3-error:' + errorPaletteDark[60] + ';' + '\n';
    css += '    --md3-on-error: ' + errorPaletteDark[100] + ';' + '\n';
    css += '    --md3-error-container: ' + errorPaletteDark[30] + ';' + '\n';
    css += '    --md3-on-error-container: ' + errorPaletteDark[80] + ';' + '\n';
    css += '    --md3-outline: ' + neutralVariantPaletteDark[80] + ';' + '\n';
    css += '    --md3-outline-variant: ' + neutralVariantPaletteDark[95] + ';' + '\n';
    css += '    --md3-shadow: ' + neutralPaletteDark[100] + ';' + '\n';
    css += '    --md3-scrim: ' + neutralPaletteDark[100] + ';' + '\n';
    css += '    --md3-inverse-surface: ' + neutralPaletteDark[70] + ';' + '\n';
    css += '    --md3-inverse-surface-rgb: ' + Object.values(hexToRgb(neutralPaletteDark[70])).join(', ') + ';' + '\n';
    css += '    --md3-inverse-on-surface: ' + neutralPaletteDark[20] + ';' + '\n';
    css += '    --md3-inverse-on-surface-rgb: ' + Object.values(hexToRgb(neutralPaletteDark[20])).join(', ') + ';' + '\n';
    css += '    --md3-inverse-primary: ' + primaryPaletteDark[40] + ';' + '\n';
    css += '    --md3-primary-fixed: ' + primaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-primary-fixed-dim: ' + primaryPaletteDark[20] + ';' + '\n';
    css += '    --md3-on-primary-fixed: ' + primaryPaletteDark[90] + ';' + '\n';
    css += '    --md3-on-primary-fixed-variant: ' + primaryPaletteDark[80] + ';' + '\n';
    css += '    --md3-secondary-fixed: ' + secondaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-secondary-fixed-dim: ' + secondaryPaletteDark[20] + ';' + '\n';
    css += '    --md3-on-secondary-fixed: ' + secondaryPaletteDark[90] + ';' + '\n';
    css += '    --md3-on-secondary-fixed-variant: ' + secondaryPaletteDark[80] + ';' + '\n';
    css += '    --md3-tertiary-fixed: ' + tertiaryPaletteDark[30] + ';' + '\n';
    css += '    --md3-tertiary-fixed-dim: ' + tertiaryPaletteDark[20] + ';' + '\n';
    css += '    --md3-on-tertiary-fixed: ' + tertiaryPaletteDark[90] + ';' + '\n';
    css += '    --md3-on-tertiary-fixed-variant: ' + tertiaryPaletteDark[80] + ';' + '\n';
    css += '    --md3-surface-dim: ' + neutralPaletteDark[95] + ';' + '\n';
    css += '    --md3-surface-bright: ' + neutralPaletteDark[98] + ';' + '\n';
    css += '    --md3-surface-container-lowest: ' + neutralPaletteDark[100] + ';' + '\n';
    css += '    --md3-surface-container-low: ' + neutralPaletteDark[96] + ';' + '\n';
    css += '    --md3-surface-container: ' + neutralPaletteDark[94] + ';' + '\n';
    css += '    --md3-surface-container-high: ' + neutralPaletteDark[92] + ';' + '\n';
    css += '    --md3-surface-container-highest: ' + neutralPaletteDark[90] + ';' + '\n';

    css += '}' + '\n';

    return css;
}

// 保存 CSS 到 localStorage
function saveCustomCSS(css) {
  localStorage.setItem('custom_css', css);
  applyCustomCSS(css);
}

// 应用 CSS
function applyCustomCSS(css) {
  let styleTag = document.getElementById('custom_style_tag');
  
  if (!styleTag) {
    // 如果不存在，创建新的 style 标签
    styleTag = document.createElement('style');
    styleTag.id = 'custom_style_tag';
    document.head.appendChild(styleTag);
  }
  
  // 设置 CSS 内容
  styleTag.textContent = css;
}

// 页面加载时应用已保存的 CSS
window.addEventListener('DOMContentLoaded', () => {
  const savedCSS = localStorage.getItem('custom_css');
  if (savedCSS) {
    applyCustomCSS(savedCSS);
  }
});