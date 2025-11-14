import React from 'react';
import MainLayout from '../Layouts/MainLayout';
import { Link } from '@inertiajs/react';
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, CartesianGrid, ResponsiveContainer, Tooltip } from 'recharts';

export default function Dashboard({ summary, monthlyData, recentTransactions }) {
    // Calculate total and percentages for category pie chart
    const totalAmount = (summary?.bfko?.total || 0) + (summary?.serviceFee?.total || 0) + (summary?.ccCard?.total || 0);
    
    // Only show categories with value > 0
    const categoryData = [
        { 
            name: 'BFKO', 
            value: summary?.bfko?.total || 0,
            percentage: totalAmount > 0 ? ((summary?.bfko?.total || 0) / totalAmount * 100).toFixed(1) : 0,
            color: '#fbbf24' 
        },
        { 
            name: 'CC Card', 
            value: summary?.ccCard?.total || 0,
            percentage: totalAmount > 0 ? ((summary?.ccCard?.total || 0) / totalAmount * 100).toFixed(1) : 0,
            color: '#22d3ee' 
        },
        { 
            name: 'Service Fee', 
            value: summary?.serviceFee?.total || 0,
            percentage: totalAmount > 0 ? ((summary?.serviceFee?.total || 0) / totalAmount * 100).toFixed(1) : 0,
            color: '#22c55e' 
        }
    ].filter(item => item.value > 0); // Filter out zero values to prevent 100% display issue

    // Format currency for display
    const formatCurrency = (amount) => {
        if (amount >= 1000000000) {
            return 'Rp' + (amount / 1000000000).toFixed(1) + 'M';
        } else if (amount >= 1000000) {
            return 'Rp' + (amount / 1000000).toFixed(1) + 'Jt';
        }
        return 'Rp' + amount.toLocaleString('id-ID');
    };

    return (
        <MainLayout>
            <div className="p-8 bg-gradient-to-br from-gray-50 to-cyan-50/30 min-h-screen">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-4xl font-bold text-gray-800 mb-2">Dashboard Overview</h1>
                    <p className="text-gray-600">Monitoring Dashboard PLN - Real-time Data Analytics</p>
                </div>

                {/* Quick Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    {/* BFKO Card */}
                    <Link href="/bfko" className="group">
                        <div className="bg-gradient-to-br from-yellow-400 to-amber-500 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105 border-l-4 border-yellow-600">
                            <div className="flex items-center justify-between mb-4">
                                <div className="w-14 h-14 bg-white/30 backdrop-blur-md rounded-xl flex items-center justify-center">
                                    <span className="text-3xl">⚡</span>
                                </div>
                                <span className="text-white/80 text-sm font-medium">BFKO Monitoring</span>
                            </div>
                            <p className="text-white text-3xl font-bold mb-2">{formatCurrency(summary?.bfko?.total || 0)}</p>
                            <div className="flex items-center justify-between text-white/90 text-sm">
                                <span>{summary?.bfko?.count || 0} transaksi</span>
                                <span>{summary?.bfko?.employees || 0} pegawai</span>
                            </div>
                        </div>
                    </Link>

                    {/* CC Card */}
                    <Link href="/cc-card" className="group">
                        <div className="bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105 border-l-4 border-blue-600">
                            <div className="flex items-center justify-between mb-4">
                                <div className="w-14 h-14 bg-white/30 backdrop-blur-md rounded-xl flex items-center justify-center">
                                    <span className="text-3xl">💳</span>
                                </div>
                                <span className="text-white/80 text-sm font-medium">CC Card Monitoring</span>
                            </div>
                            <p className="text-white text-3xl font-bold mb-2">{formatCurrency(summary?.ccCard?.total || 0)}</p>
                            <div className="flex items-center justify-between text-white/90 text-sm">
                                <span>{summary?.ccCard?.count || 0} transaksi</span>
                                <span>{summary?.ccCard?.employees || 0} pegawai</span>
                            </div>
                        </div>
                    </Link>

                    {/* Service Fee Card */}
                    <Link href="/service-fee" className="group">
                        <div className="bg-gradient-to-br from-green-400 to-emerald-500 rounded-2xl p-6 shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105 border-l-4 border-green-600">
                            <div className="flex items-center justify-between mb-4">
                                <div className="w-14 h-14 bg-white/30 backdrop-blur-md rounded-xl flex items-center justify-center">
                                    <span className="text-3xl">🏨</span>
                                </div>
                                <span className="text-white/80 text-sm font-medium">Service Fee Monitoring</span>
                            </div>
                            <p className="text-white text-3xl font-bold mb-2">{formatCurrency(summary?.serviceFee?.total || 0)}</p>
                            <div className="flex items-center justify-between text-white/90 text-sm">
                                <span>🏨 {summary?.serviceFee?.hotel || 0} hotel</span>
                                <span>✈️ {summary?.serviceFee?.flight || 0} pesawat</span>
                            </div>
                        </div>
                    </Link>
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    {/* Category Distribution */}
                    <div className="bg-white rounded-2xl p-6 shadow-lg">
                        <h3 className="text-xl font-bold text-gray-800 mb-2">Distribution by Category</h3>
                        <p className="text-xs text-gray-500 mb-6">Based on total transaction value (Rupiah)</p>
                        <div className="flex items-center justify-center">
                            <div className="w-48 h-48">
                                {categoryData.length > 0 ? (
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={categoryData}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={60}
                                                outerRadius={90}
                                                paddingAngle={3}
                                                dataKey="value"
                                            >
                                                {categoryData.map((entry, index) => (
                                                    <Cell key={`cell-${index}`} fill={entry.color} />
                                                ))}
                                            </Pie>
                                            <Tooltip formatter={(value) => formatCurrency(value)} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                ) : (
                                    <div className="flex items-center justify-center h-full text-gray-400">
                                        <p>No data available</p>
                                    </div>
                                )}
                            </div>
                            <div className="ml-8 space-y-4">
                                <Link href="/bfko" className="block hover:opacity-70 transition group">
                                    <div className="flex items-center justify-between mb-1">
                                        <div className="flex items-center">
                                            <div className="w-4 h-4 bg-yellow-400 rounded-full mr-3"></div>
                                            <span className="text-sm font-medium text-gray-700">BFKO</span>
                                        </div>
                                        <span className="text-sm font-bold text-gray-800 ml-4">
                                            {totalAmount > 0 ? ((summary?.bfko?.total || 0) / totalAmount * 100).toFixed(1) : 0}%
                                        </span>
                                    </div>
                                    <div className="ml-7 text-xs text-gray-500">
                                        {formatCurrency(summary?.bfko?.total || 0)} • {summary?.bfko?.count || 0} records
                                    </div>
                                </Link>
                                <Link href="/cc-card" className="block hover:opacity-70 transition group">
                                    <div className="flex items-center justify-between mb-1">
                                        <div className="flex items-center">
                                            <div className="w-4 h-4 bg-cyan-400 rounded-full mr-3"></div>
                                            <span className="text-sm font-medium text-gray-700">CC Card</span>
                                        </div>
                                        <span className="text-sm font-bold text-gray-800 ml-4">
                                            {totalAmount > 0 ? ((summary?.ccCard?.total || 0) / totalAmount * 100).toFixed(1) : 0}%
                                        </span>
                                    </div>
                                    <div className="ml-7 text-xs text-gray-500">
                                        {formatCurrency(summary?.ccCard?.total || 0)} • {summary?.ccCard?.count || 0} records
                                    </div>
                                </Link>
                                <Link href="/service-fee" className="block hover:opacity-70 transition group">
                                    <div className="flex items-center justify-between mb-1">
                                        <div className="flex items-center">
                                            <div className="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                                            <span className="text-sm font-medium text-gray-700">Service Fee</span>
                                        </div>
                                        <span className="text-sm font-bold text-gray-800 ml-4">
                                            {totalAmount > 0 ? ((summary?.serviceFee?.total || 0) / totalAmount * 100).toFixed(2) : 0}%
                                        </span>
                                    </div>
                                    <div className="ml-7 text-xs text-gray-500">
                                        {formatCurrency(summary?.serviceFee?.total || 0)} • {summary?.serviceFee?.count || 0} records
                                    </div>
                                </Link>
                                <div className="mt-3 pt-3 border-t border-gray-200">
                                    <div className="text-xs text-gray-600">
                                        <span className="font-semibold">Grand Total: </span>
                                        {formatCurrency(totalAmount)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Monthly Trend - All Categories */}
                    <div className="bg-white rounded-2xl p-6 shadow-lg">
                        <h3 className="text-xl font-bold text-gray-800 mb-2">Monthly Trend Comparison</h3>
                        <p className="text-xs text-gray-500 mb-6">All categories by month</p>
                        <ResponsiveContainer width="100%" height={250}>
                            <BarChart data={monthlyData || []}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                <XAxis 
                                    dataKey="month" 
                                    tick={{ fill: '#6b7280', fontSize: 12 }}
                                />
                                <YAxis 
                                    tick={{ fill: '#6b7280', fontSize: 12 }}
                                    tickFormatter={(value) => {
                                        if (value >= 1000000000) return `${(value/1000000000).toFixed(1)}M`;
                                        if (value >= 1000000) return `${(value/1000000).toFixed(0)}Jt`;
                                        return value;
                                    }}
                                />
                                <Tooltip 
                                    formatter={(value, name) => {
                                        const labels = {
                                            'bfko': 'BFKO',
                                            'ccCard': 'CC Card',
                                            'serviceFee': 'Service Fee'
                                        };
                                        return [formatCurrency(value), labels[name] || name];
                                    }}
                                    contentStyle={{ borderRadius: '8px', border: '1px solid #e5e7eb' }}
                                />
                                <Bar dataKey="bfko" fill="#fbbf24" radius={[4, 4, 0, 0]} name="BFKO" />
                                <Bar dataKey="ccCard" fill="#22d3ee" radius={[4, 4, 0, 0]} name="CC Card" />
                                <Bar dataKey="serviceFee" fill="#22c55e" radius={[4, 4, 0, 0]} name="Service Fee" />
                            </BarChart>
                        </ResponsiveContainer>
                        
                        {/* Legend */}
                        <div className="flex items-center justify-center gap-6 mt-4">
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-yellow-400 rounded"></div>
                                <span className="text-xs text-gray-600">BFKO</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-cyan-400 rounded"></div>
                                <span className="text-xs text-gray-600">CC Card</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-green-500 rounded"></div>
                                <span className="text-xs text-gray-600">Service Fee</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Recent Transactions Table */}
                <div className="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div className="p-6 border-b border-gray-200 bg-gradient-to-r from-cyan-50 to-teal-50">
                        <h2 className="text-xl font-bold text-gray-800">Recent Transactions</h2>
                        <p className="text-sm text-gray-600 mt-1">Latest activity across all monitoring systems</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="text-left py-4 px-6 font-semibold text-gray-700 text-sm">Person</th>
                                    <th className="text-left py-4 px-6 font-semibold text-gray-700 text-sm">Date</th>
                                    <th className="text-left py-4 px-6 font-semibold text-gray-700 text-sm">Category</th>
                                    <th className="text-left py-4 px-6 font-semibold text-gray-700 text-sm">Description</th>
                                    <th className="text-right py-4 px-6 font-semibold text-gray-700 text-sm">Total</th>
                                    <th className="text-center py-4 px-6 font-semibold text-gray-700 text-sm">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentTransactions && recentTransactions.length > 0 ? (
                                    recentTransactions.map((payment, index) => (
                                        <tr key={index} className="border-b border-gray-100 hover:bg-cyan-50/30 transition-colors">
                                            <td className="py-4 px-6 font-medium text-gray-800">{payment.person}</td>
                                            <td className="py-4 px-6 text-gray-600 text-sm">{payment.date}</td>
                                            <td className="py-4 px-6">
                                                <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                                                    payment.category === 'BFKO' ? 'bg-yellow-100 text-yellow-800' :
                                                    payment.category === 'CC Card' ? 'bg-cyan-100 text-cyan-800' :
                                                    'bg-green-100 text-green-800'
                                                }`}>
                                                    {payment.category === 'BFKO' && '⚡ '}
                                                    {payment.category === 'CC Card' && '💳 '}
                                                    {payment.category === 'Service Fee' && '🏨 '}
                                                    {payment.category}
                                                </span>
                                            </td>
                                            <td className="py-4 px-6 text-gray-600 text-sm max-w-md truncate">{payment.description}</td>
                                            <td className="py-4 px-6 font-bold text-gray-800 text-right">{payment.total}</td>
                                            <td className="py-4 px-6 text-center">
                                                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                                    ✓ {payment.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="py-8 text-center text-gray-500">
                                            No recent transactions found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    
                    {/* View All Links */}
                    <div className="p-6 bg-gray-50 border-t border-gray-200">
                        <div className="flex items-center justify-center gap-6">
                            <Link 
                                href="/bfko"
                                className="text-sm font-semibold text-yellow-600 hover:text-yellow-700 transition flex items-center gap-2"
                            >
                                View All BFKO
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                            <span className="text-gray-300">|</span>
                            <Link 
                                href="/cc-card"
                                className="text-sm font-semibold text-cyan-600 hover:text-cyan-700 transition flex items-center gap-2"
                            >
                                View All CC Card
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                            <span className="text-gray-300">|</span>
                            <Link 
                                href="/service-fee"
                                className="text-sm font-semibold text-green-600 hover:text-green-700 transition flex items-center gap-2"
                            >
                                View All Service Fee
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
