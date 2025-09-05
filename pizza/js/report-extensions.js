/**
 * Report Extensions Module
 * Provides additional analytics and export functionality for the pizza dashboard
 */

/**
 * Compute extended metrics from API data
 * @param {Object} data - API response data containing sections: dnesni_prodeje, kategorie, top_orders, trendy, food_cost_analysis, top_margin_items, payment_methods, employees
 * @returns {Object} Extended metrics object
 */
function computeExtendedMetrics(data) {
    if (!data || !data.data) return {};
    
    const apiData = data.data;
    const dnesni = apiData.dnesni_prodeje || {};
    const produkty = dnesni.produkty || [];
    const trendy = apiData.trendy || [];
    const foodCostAnalysis = apiData.food_cost_analysis;
    const topMarginItems = apiData.top_margin_items || [];
    const paymentMethods = apiData.payment_methods || [];
    
    const metrics = {};
    
    // Basic metrics
    metrics.totalRevenue = dnesni.total || 0;
    metrics.orders = dnesni.pocet || 0;
    metrics.itemsTotal = produkty.reduce((sum, p) => sum + parseInt(p.pocet || 0), 0);
    metrics.aov = metrics.orders > 0 ? (metrics.totalRevenue / metrics.orders) : 0;
    
    // Top 5 Revenue Percentage
    if (produkty.length > 0) {
        const sortedByRevenue = [...produkty].sort((a, b) => parseFloat(b.trzba || 0) - parseFloat(a.trzba || 0));
        const top5Revenue = sortedByRevenue.slice(0, 5).reduce((sum, p) => sum + parseFloat(p.trzba || 0), 0);
        metrics.top5RevenuePct = metrics.totalRevenue > 0 ? (top5Revenue / metrics.totalRevenue) * 100 : 0;
    }
    
    // Gross Margin from food cost analysis
    if (foodCostAnalysis && foodCostAnalysis.total) {
        metrics.grossMargin = parseFloat(foodCostAnalysis.total.margin || 0);
        metrics.grossMarginPct = 100 - parseFloat(foodCostAnalysis.total.cost_percent || 0);
    }
    
    // Top 5 Margin Percentage
    if (topMarginItems.length > 0) {
        const top5Margin = topMarginItems.slice(0, 5).reduce((sum, item) => sum + parseFloat(item.total_margin || 0), 0);
        metrics.top5MarginPct = metrics.grossMargin > 0 ? (top5Margin / metrics.grossMargin) * 100 : 0;
    }
    
    // Revenue Growth vs Previous Day
    if (trendy.length >= 2) {
        // trendy array should be sorted by date, get last two entries
        const currentDay = trendy[trendy.length - 1];
        const previousDay = trendy[trendy.length - 2];
        
        if (currentDay && previousDay && parseFloat(previousDay.total) > 0) {
            const currentTotal = parseFloat(currentDay.total || 0);
            const previousTotal = parseFloat(previousDay.total || 0);
            metrics.revenueGrowthPct = ((currentTotal - previousTotal) / previousTotal) * 100;
        }
    }
    
    // Payment Method Shares
    if (paymentMethods.length > 0) {
        const totalPayments = paymentMethods.reduce((sum, pm) => sum + parseFloat(pm.total || 0), 0);
        
        const cashPayment = paymentMethods.find(pm => pm.payment_method === 'cash');
        const cardPayment = paymentMethods.find(pm => pm.payment_method === 'card');
        
        if (totalPayments > 0) {
            metrics.cashShare = cashPayment ? (parseFloat(cashPayment.total) / totalPayments) * 100 : 0;
            metrics.cardShare = cardPayment ? (parseFloat(cardPayment.total) / totalPayments) * 100 : 0;
        }
    }
    
    return metrics;
}

/**
 * Export data to CSV with multiple sections
 * @param {Object} data - API response data
 */
function exportToCsvData(data) {
    if (!data || !data.data) {
        alert('Žádná data k exportu');
        return;
    }
    
    const apiData = data.data;
    const extendedMetrics = computeExtendedMetrics(data);
    
    let csvContent = '\uFEFF'; // UTF-8 BOM
    
    // SUMMARY Section
    csvContent += '# SUMMARY\n';
    csvContent += 'Metric,Value\n';
    csvContent += `Total Revenue,${extendedMetrics.totalRevenue || 0}\n`;
    csvContent += `Orders Count,${extendedMetrics.orders || 0}\n`;
    csvContent += `Items Total,${extendedMetrics.itemsTotal || 0}\n`;
    csvContent += `Average Order Value,${extendedMetrics.aov ? extendedMetrics.aov.toFixed(2) : 0}\n`;
    csvContent += `Top 5 Revenue %,${extendedMetrics.top5RevenuePct ? extendedMetrics.top5RevenuePct.toFixed(2) : 'N/A'}\n`;
    csvContent += `Gross Margin CZK,${extendedMetrics.grossMargin || 'N/A'}\n`;
    csvContent += `Gross Margin %,${extendedMetrics.grossMarginPct ? extendedMetrics.grossMarginPct.toFixed(2) : 'N/A'}\n`;
    csvContent += `Revenue Growth %,${extendedMetrics.revenueGrowthPct ? extendedMetrics.revenueGrowthPct.toFixed(2) : 'N/A'}\n`;
    csvContent += `Cash Share %,${extendedMetrics.cashShare ? extendedMetrics.cashShare.toFixed(2) : 'N/A'}\n`;
    csvContent += `Card Share %,${extendedMetrics.cardShare ? extendedMetrics.cardShare.toFixed(2) : 'N/A'}\n`;
    csvContent += '\n';
    
    // PRODUKTY Section
    if (apiData.dnesni_prodeje && apiData.dnesni_prodeje.produkty) {
        csvContent += '# PRODUKTY\n';
        csvContent += 'Name,Category,Quantity,Revenue\n';
        apiData.dnesni_prodeje.produkty.forEach(product => {
            csvContent += `"${product.nazev || ''}","${product.kategorie || ''}",${product.pocet || 0},${product.trzba || 0}\n`;
        });
        csvContent += '\n';
    }
    
    // KATEGORIE Section
    if (apiData.kategorie) {
        csvContent += '# KATEGORIE\n';
        csvContent += 'Category,Revenue,Percentage\n';
        apiData.kategorie.forEach(category => {
            csvContent += `"${category.kategorie || ''}",${category.trzba || 0},${category.percentage || 0}\n`;
        });
        csvContent += '\n';
    }
    
    // TRENDY Section
    if (apiData.trendy) {
        csvContent += '# TRENDY\n';
        csvContent += 'Date,Revenue,Orders\n';
        apiData.trendy.forEach(trend => {
            csvContent += `"${trend.datum || ''}",${trend.total || 0},${trend.pocet || 0}\n`;
        });
        csvContent += '\n';
    }
    
    // TOP_ORDERS Section
    if (apiData.top_orders) {
        csvContent += '# TOP_ORDERS\n';
        csvContent += 'Order ID,Table,Amount,Payment Method,Paid At,Employee\n';
        apiData.top_orders.forEach(order => {
            csvContent += `${order.order_id || ''},"Table ${order.table_code || ''}",${order.amount || 0},"${order.payment_method || ''}","${order.paid_at || ''}","${order.employee_name || 'N/A'}"\n`;
        });
        csvContent += '\n';
    }
    
    // TOP_MARGIN Section
    if (apiData.top_margin_items) {
        csvContent += '# TOP_MARGIN\n';
        csvContent += 'Item Name,Type,Quantity,Revenue,Cost,Margin,Margin %\n';
        apiData.top_margin_items.forEach(item => {
            csvContent += `"${item.nazev || ''}","${item.item_type || ''}",${item.pocet || 0},${item.trzba || 0},${item.total_cost || 0},${item.total_margin || 0},${item.margin_percent || 0}\n`;
        });
        csvContent += '\n';
    }
    
    // PAYMENTS Section
    if (apiData.payment_methods) {
        csvContent += '# PAYMENTS\n';
        csvContent += 'Payment Method,Total,Count,Percentage\n';
        apiData.payment_methods.forEach(payment => {
            csvContent += `"${payment.payment_method || ''}",${payment.total || 0},${payment.count || 0},${payment.percentage || 0}\n`;
        });
        csvContent += '\n';
    }
    
    // Create and download the CSV file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        
        const now = new Date();
        const timestamp = now.toISOString().slice(0, 19).replace(/:/g, '-');
        link.setAttribute('download', `pizza-report-${timestamp}.csv`);
        
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        alert('CSV export není podporován v tomto prohlížeči');
    }
}

/**
 * Export current dashboard to PDF
 * @param {Object} data - API response data for reference
 */
function exportToPdfData(data) {
    if (!data || !data.data) {
        alert('Žádná data k exportu');
        return;
    }
    
    // Load required libraries if not already loaded
    loadRequiredLibraries().then(() => {
        const reportsElement = document.getElementById('reports');
        
        if (!reportsElement) {
            alert('Nelze najít obsah k exportu');
            return;
        }
        
        // Temporarily hide export buttons and add PDF-specific styles
        const style = document.createElement('style');
        style.innerHTML = `
            .export-btn, .filters, .tabs { display: none !important; }
            .chart-box { break-inside: avoid; margin-bottom: 20px; }
            .stats-grid { break-inside: avoid; }
            .additional-stats { break-inside: avoid; }
        `;
        document.head.appendChild(style);
        
        const opt = {
            margin: 10,
            filename: `pizza-report-${new Date().toISOString().slice(0, 10)}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                allowTaint: true,
                scrollX: 0,
                scrollY: 0
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait',
                compress: true
            }
        };
        
        // Use html2pdf if available, otherwise fallback to html2canvas + jsPDF
        if (typeof html2pdf !== 'undefined') {
            html2pdf().set(opt).from(reportsElement).save().finally(() => {
                document.head.removeChild(style);
            });
        } else {
            // Fallback implementation
            html2canvas(reportsElement, opt.html2canvas).then(canvas => {
                const imgData = canvas.toDataURL('image/jpeg', 0.98);
                const pdf = new jsPDF(opt.jsPDF.orientation, opt.jsPDF.unit, opt.jsPDF.format);
                
                const imgWidth = 210;
                const pageHeight = 295;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 0;
                
                pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                pdf.save(opt.filename);
                document.head.removeChild(style);
            }).catch(error => {
                console.error('PDF export error:', error);
                alert('Chyba při exportu PDF: ' + error.message);
                document.head.removeChild(style);
            });
        }
    });
}

/**
 * Load required libraries for PDF export
 * @returns {Promise}
 */
function loadRequiredLibraries() {
    return new Promise((resolve) => {
        const scriptsToLoad = [];
        
        // Check if html2canvas is loaded
        if (typeof html2canvas === 'undefined') {
            scriptsToLoad.push('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
        }
        
        // Check if jsPDF is loaded
        if (typeof jsPDF === 'undefined' && typeof window.jspdf === 'undefined') {
            scriptsToLoad.push('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
        }
        
        if (scriptsToLoad.length === 0) {
            resolve();
            return;
        }
        
        let loaded = 0;
        scriptsToLoad.forEach(src => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = () => {
                loaded++;
                if (loaded === scriptsToLoad.length) {
                    // Setup jsPDF if needed
                    if (typeof jsPDF === 'undefined' && typeof window.jspdf !== 'undefined') {
                        window.jsPDF = window.jspdf.jsPDF;
                    }
                    resolve();
                }
            };
            script.onerror = () => {
                console.error('Failed to load script:', src);
                loaded++;
                if (loaded === scriptsToLoad.length) {
                    resolve();
                }
            };
            document.head.appendChild(script);
        });
    });
}

/**
 * Generate additional mini-stat tiles HTML for extended metrics
 * @param {Object} extendedMetrics - Result from computeExtendedMetrics()
 * @returns {string} HTML string for mini-stat tiles
 */
function generateExtendedStatsHTML(extendedMetrics) {
    let html = '';
    
    // Revenue Growth tile
    if (typeof extendedMetrics.revenueGrowthPct === 'number') {
        const growthClass = extendedMetrics.revenueGrowthPct >= 0 ? 'positive' : 'negative';
        const growthIcon = extendedMetrics.revenueGrowthPct >= 0 ? '📈' : '📉';
        html += `
            <div class="mini-stat ${growthClass}">
                <div class="value">${growthIcon} ${extendedMetrics.revenueGrowthPct.toFixed(1)}%</div>
                <div class="label">Růst vs včera</div>
            </div>
        `;
    }
    
    // Top 5 Revenue Share tile
    if (typeof extendedMetrics.top5RevenuePct === 'number') {
        html += `
            <div class="mini-stat">
                <div class="value">🏆 ${extendedMetrics.top5RevenuePct.toFixed(1)}%</div>
                <div class="label">TOP 5 tržby</div>
            </div>
        `;
    }
    
    // Gross Margin tile
    if (typeof extendedMetrics.grossMarginPct === 'number') {
        const marginClass = extendedMetrics.grossMarginPct >= 70 ? 'positive' : extendedMetrics.grossMarginPct >= 60 ? '' : 'negative';
        html += `
            <div class="mini-stat ${marginClass}">
                <div class="value">💰 ${extendedMetrics.grossMarginPct.toFixed(1)}%</div>
                <div class="label">Hrubá marže</div>
            </div>
        `;
    }
    
    // Top 5 Margin Share tile
    if (typeof extendedMetrics.top5MarginPct === 'number') {
        html += `
            <div class="mini-stat">
                <div class="value">⭐ ${extendedMetrics.top5MarginPct.toFixed(1)}%</div>
                <div class="label">TOP 5 marže</div>
            </div>
        `;
    }
    
    // Payment Mix tiles
    if (typeof extendedMetrics.cashShare === 'number' && typeof extendedMetrics.cardShare === 'number') {
        html += `
            <div class="mini-stat">
                <div class="value">💵 ${extendedMetrics.cashShare.toFixed(1)}%</div>
                <div class="label">Hotovost</div>
            </div>
            <div class="mini-stat">
                <div class="value">💳 ${extendedMetrics.cardShare.toFixed(1)}%</div>
                <div class="label">Karta</div>
            </div>
        `;
    }
    
    return html;
}