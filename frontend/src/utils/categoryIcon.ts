export function categoryIcon(categorySlug?: string, categoryName?: string): string {
  const key = `${categorySlug || ''} ${categoryName || ''}`.toLowerCase()
  if (/bevand|drink|bar|cocktail|wine|birra|beer|aperit/.test(key)) return 'mdi-glass-cocktail'
  if (/dolc|dessert|gelat|sweet|cake/.test(key)) return 'mdi-cupcake'
  if (/cibo|food|snack|piatt|cucina|kitchen|mangiar/.test(key)) return 'mdi-food'
  return 'mdi-silverware-fork-knife'
}
