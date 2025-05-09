// Chart.js helper functions for PayTrack

/**
 * Create a bar chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - Array of labels for the x-axis
 * @param {Array} data - Array of data values
 * @param {string} label - Label for the dataset
 * @param {Object} options - Additional chart options
 */
function createBarChart(elementId, labels, data, label = 'Data', options = {}) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const ctx = element.getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    };
    
    // Merge default options with passed options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create the chart
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: 'rgba(26, 86, 219, 0.5)',
                borderColor: 'rgba(26, 86, 219, 1)',
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Create a line chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - Array of labels for the x-axis
 * @param {Array} data - Array of data values
 * @param {string} label - Label for the dataset
 * @param {Object} options - Additional chart options
 */
function createLineChart(elementId, labels, data, label = 'Data', options = {}) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const ctx = element.getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    };
    
    // Merge default options with passed options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create the chart
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderColor: 'rgba(26, 86, 219, 1)',
                backgroundColor: 'rgba(26, 86, 219, 0.1)',
                borderWidth: 2,
                tension: 0.1,
                fill: true
            }]
        },
        options: chartOptions
    });
}

/**
 * Create a pie chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - Array of labels
 * @param {Array} data - Array of data values
 * @param {Array} colors - Array of background colors (optional)
 * @param {Object} options - Additional chart options
 */
function createPieChart(elementId, labels, data, colors = [], options = {}) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const ctx = element.getContext('2d');
    
    // Default colors if not provided
    if (!colors || colors.length === 0) {
        colors = [
            'rgba(26, 86, 219, 0.7)',
            'rgba(14, 159, 110, 0.7)',
            'rgba(255, 90, 31, 0.7)',
            'rgba(224, 36, 36, 0.7)',
            'rgba(79, 70, 229, 0.7)',
            'rgba(16, 185, 129, 0.7)',
            'rgba(251, 191, 36, 0.7)',
            'rgba(239, 68, 68, 0.7)'
        ];
    }
    
    // Ensure we have enough colors
    while (colors.length < data.length) {
        colors = colors.concat(colors);
    }
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    };
    
    // Merge default options with passed options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create the chart
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, data.length),
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Create a doughnut chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - Array of labels
 * @param {Array} data - Array of data values
 * @param {Array} colors - Array of background colors (optional)
 * @param {Object} options - Additional chart options
 */
function createDoughnutChart(elementId, labels, data, colors = [], options = {}) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const ctx = element.getContext('2d');
    
    // Default colors if not provided
    if (!colors || colors.length === 0) {
        colors = [
            'rgba(26, 86, 219, 0.7)',
            'rgba(14, 159, 110, 0.7)',
            'rgba(255, 90, 31, 0.7)',
            'rgba(224, 36, 36, 0.7)',
            'rgba(79, 70, 229, 0.7)',
            'rgba(16, 185, 129, 0.7)',
            'rgba(251, 191, 36, 0.7)',
            'rgba(239, 68, 68, 0.7)'
        ];
    }
    
    // Ensure we have enough colors
    while (colors.length < data.length) {
        colors = colors.concat(colors);
    }
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        },
        cutout: '70%'
    };
    
    // Merge default options with passed options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create the chart
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, data.length),
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Create a multi-series bar chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - Array of labels for the x-axis
 * @param {Array} datasets - Array of dataset objects
 * @param {Object} options - Additional chart options
 */
function createMultiBarChart(elementId, labels, datasets, options = {}) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const ctx = element.getContext('2d');
    
    // Default colors
    const defaultColors = [
        { bg: 'rgba(26, 86, 219, 0.5)', border: 'rgba(26, 86, 219, 1)' },
        { bg: 'rgba(14, 159, 110, 0.5)', border: 'rgba(14, 159, 110, 1)' },
        { bg: 'rgba(255, 90, 31, 0.5)', border: 'rgba(255, 90, 31, 1)' },
        { bg: 'rgba(224, 36, 36, 0.5)', border: 'rgba(224, 36, 36, 1)' }
    ];
    
    // Format datasets with colors if not provided
    const formattedDatasets = datasets.map((dataset, index) => {
        const colorIndex = index % defaultColors.length;
        return {
            label: dataset.label || `Dataset ${index + 1}`,
            data: dataset.data,
            backgroundColor: dataset.backgroundColor || defaultColors[colorIndex].bg,
            borderColor: dataset.borderColor || defaultColors[colorIndex].border,
            borderWidth: dataset.borderWidth || 1
        };
    });
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    };
    
    // Merge default options with passed options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create the chart
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: formattedDatasets
        },
        options: chartOptions
    });
}

/**
 * Create a multi-series line chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - Array of labels for the x-axis
 * @param {Array} datasets - Array of dataset objects
 * @param {Object} options - Additional chart options
 */
function createMultiLineChart(elementId, labels, datasets, options = {}) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const ctx = element.getContext('2d');
    
    // Default colors
    const defaultColors = [
        { border: 'rgba(26, 86, 219, 1)', bg: 'rgba(26, 86, 219, 0.1)' },
        { border: 'rgba(14, 159, 110, 1)', bg: 'rgba(14, 159, 110, 0.1)' },
        { border: 'rgba(255, 90, 31, 1)', bg: 'rgba(255, 90, 31, 0.1)' },
        { border: 'rgba(224, 36, 36, 1)', bg: 'rgba(224, 36, 36, 0.1)' }
    ];
    
    // Format datasets with colors if not provided
    const formattedDatasets = datasets.map((dataset, index) => {
        const colorIndex = index % defaultColors.length;
        return {
            label: dataset.label || `Dataset ${index + 1}`,
            data: dataset.data,
            borderColor: dataset.borderColor || defaultColors[colorIndex].border,
            backgroundColor: dataset.backgroundColor || defaultColors[colorIndex].bg,
            borderWidth: dataset.borderWidth || 2,
            tension: dataset.tension || 0.1,
            fill: dataset.fill !== undefined ? dataset.fill : true
        };
    });
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    };
    
    // Merge default options with passed options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create the chart
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: formattedDatasets
        },
        options: chartOptions
    });
}

/**
 * Create a financial status chart for dashboard
 * @param {string} elementId - The ID of the canvas element
 * @param {Object} stats - Object with payment statistics
 */
function createFinancialStatusChart(elementId, stats) {
    const labels = ['Completed', 'Pending', 'Rejected', 'Refunded'];
    const data = [
        stats.completed_payments_count || 0,
        stats.pending_payments_count || 0,
        stats.rejected_payments_count || 0,
        stats.refunded_payments_count || 0
    ];
    const colors = [
        'rgba(14, 159, 110, 0.7)',  // Green for completed
        'rgba(255, 90, 31, 0.7)',   // Orange for pending
        'rgba(224, 36, 36, 0.7)',   // Red for rejected
        'rgba(63, 131, 248, 0.7)'   // Blue for refunded
    ];
    
    createDoughnutChart(elementId, labels, data, colors, {
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    });
}

/**
 * Create a monthly payments trend chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} months - Array of month labels
 * @param {Array} amounts - Array of payment amounts
 */
function createMonthlyTrendChart(elementId, months, amounts) {
    createLineChart(elementId, months, amounts, 'Monthly Payments', {
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        },
        tooltips: {
            callbacks: {
                label: function(context) {
                    return '₱' + context.raw.toLocaleString();
                }
            }
        }
    });
}

/**
 * Create a payment categories distribution chart
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} categories - Array of category names
 * @param {Array} amounts - Array of amounts for each category
 */
function createCategoriesChart(elementId, categories, amounts) {
    createPieChart(elementId, categories, amounts, [], {
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    });
}
